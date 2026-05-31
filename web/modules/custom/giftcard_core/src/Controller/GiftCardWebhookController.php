<?php

namespace Drupal\giftcard_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
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

  private const PROCESSED_COLLECTION = 'giftcard_core.processed';

  /**
   * Constructs a new GiftCardWebhookController.
   *
   * @param \Drupal\giftcard_core\GiftCardService $giftCardService
   *   The gift card service.
   * @param \Drupal\giftcard_core\PaymentClientInterface $paymentClient
   *   The payment client.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $keyValueExpirable
   *   The expirable key-value store factory.
   */
  public function __construct(
    private readonly GiftCardService $giftCardService,
    private readonly PaymentClientInterface $paymentClient,
    private readonly MailManagerInterface $mailManager,
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirable,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self(
      $container->get('giftcard_core.gift_card_service'),
      $container->get('giftcard_core.payment_client'),
      $container->get('plugin.manager.mail'),
      $container->get('keyvalue.expirable'),
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
    if (!$this->paymentClient->verifyWebhook($request)) {
      $this->getLogger('giftcard_core')->warning('Received webhook with invalid or replayed signature.');
      return new Response('Forbidden', Response::HTTP_FORBIDDEN);
    }

    $paymentId = $this->paymentClient->extractCompletedPaymentId($request);
    if ($paymentId === NULL) {
      return new Response('OK', Response::HTTP_OK);
    }

    // Explicit idempotency check: return 200 immediately if this payment was
    // already processed, regardless of whether checkout data still exists.
    $processed = $this->keyValueExpirable->get(self::PROCESSED_COLLECTION);
    if ($processed->get($paymentId) !== NULL) {
      return new Response('OK', Response::HTTP_OK);
    }

    $checkoutData = $this->giftCardService->getCheckoutDataByPaymentId($paymentId);
    if ($checkoutData === NULL) {
      $this->getLogger('giftcard_core')->warning(
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
    // Mark as processed for 24 hours so replay webhooks are rejected even
    // after the checkout data has been deleted.
    $processed->setWithExpire($paymentId, TRUE, 86400);

    $langcode = $this->config('system.site')->get('langcode');

    try {
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
    }
    catch (\Exception $e) {
      // Entity is already saved; log the mail failure but still acknowledge
      // the webhook so the provider does not keep retrying.
      $this->getLogger('giftcard_core')->error(
        'Gift card @code created but confirmation emails failed: @message',
        ['@code' => $giftCard->getCode(), '@message' => $e->getMessage()]
      );
    }

    return new Response('OK', Response::HTTP_OK);
  }

}
