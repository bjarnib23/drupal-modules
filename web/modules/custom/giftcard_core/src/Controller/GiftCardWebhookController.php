<?php

namespace Drupal\giftcard_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\giftcard_core\GiftCardService;
use Drupal\giftcard_core\PaymentClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives and processes payment webhooks.
 */
class GiftCardWebhookController extends ControllerBase {

  /**
   * Constructs a new GiftCardWebhookController.
   *
   * @param \Drupal\giftcard_core\GiftCardService $giftCardService
   *   The gift card service.
   * @param \Drupal\giftcard_core\PaymentClientInterface $paymentClient
   *   The payment client.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   */
  public function __construct(
    private readonly GiftCardService $giftCardService,
    private readonly PaymentClientInterface $paymentClient,
    private readonly MailManagerInterface $mailManager,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('giftcard_core.gift_card_service'),
      $container->get('giftcard_core.payment_client'),
      $container->get('plugin.manager.mail'),
      $container->get('logger.factory'),
    );
  }

  /**
   * Handles an inbound payment webhook request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An HTTP response.
   */
  public function receive(Request $request): Response {
    $body      = $request->getContent();
    $signature = $request->headers->get('rapyd-signature', '');

    if (!$this->paymentClient->verifyWebhookSignature($body, $signature)) {
      $this->loggerFactory->get('giftcard_core')->warning('Received webhook with invalid signature.');
      return new Response('Forbidden', Response::HTTP_FORBIDDEN);
    }

    $payload = json_decode($body, TRUE);
    $type    = $payload['type'] ?? '';

    if ($type !== 'PAYMENT_COMPLETED') {
      return new Response('OK', Response::HTTP_OK);
    }

    $paymentId = $payload['data']['id'] ?? NULL;
    if ($paymentId === NULL) {
      $this->loggerFactory->get('giftcard_core')->error('Webhook PAYMENT_COMPLETED missing payment ID.');
      return new Response('Bad Request', Response::HTTP_BAD_REQUEST);
    }

    $checkoutData = $this->giftCardService->getCheckoutDataByPaymentId($paymentId);
    if ($checkoutData === NULL) {
      $this->loggerFactory->get('giftcard_core')->warning(
        'No checkout data found for payment @id.',
        ['@id' => $paymentId]
      );
      return new Response('OK', Response::HTTP_OK);
    }

    $giftCard = $this->giftCardService->createGiftCard($checkoutData);

    if ($giftCard === NULL) {
      return new Response('Internal Server Error', Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    $this->giftCardService->deleteCheckoutDataByPaymentId($paymentId);

    $langcode = $this->config('system.site')->get('langcode');

    $this->mailManager->mail(
      'giftcard_core',
      'gift_card_sender',
      $giftCard->getSenderEmail(),
      $langcode,
      ['gift_card' => $giftCard]
    );

    $this->mailManager->mail(
      'giftcard_core',
      'gift_card_recipient',
      $giftCard->getRecipientEmail(),
      $langcode,
      ['gift_card' => $giftCard]
    );

    return new Response('OK', Response::HTTP_OK);
  }

}
