<?php

namespace Drupal\rapyd_checkout\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\rapyd_checkout\RapydClient;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides the Rapyd Hosted Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "rapyd_checkout",
 *   label = "Rapyd Hosted Checkout",
 *   display_label = "Pay with Rapyd",
 *   forms = {
 *     "offsite-payment" = "Drupal\rapyd_checkout\PluginForm\RapydCheckoutForm",
 *   },
 *   payment_method_types = {"credit_card"},
 * )
 */
class RapydCheckout extends OffsitePaymentGatewayBase implements SupportsNotificationsInterface {

  /**
   * The key repository for loading API credentials.
   */
  protected KeyRepositoryInterface $keyRepository;

  /**
   * The HTTP client.
   */
  protected ClientInterface $httpClient;

  /**
   * The logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance                = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->keyRepository = $container->get('key.repository');
    $instance->httpClient    = $container->get('http_client');
    $instance->logger        = $container->get('logger.channel.rapyd_checkout');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'access_key_id' => '',
      'secret_key_id' => '',
      'currency'      => '',
      'country'       => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['access_key_id'] = [
      '#type'          => 'key_select',
      '#title'         => $this->t('Access key'),
      '#description'   => $this->t('Select the Key entity that holds your Rapyd access key.'),
      '#default_value' => $this->configuration['access_key_id'],
      '#empty_option'  => $this->t('- Select a key -'),
      '#required'      => TRUE,
    ];

    $form['secret_key_id'] = [
      '#type'          => 'key_select',
      '#title'         => $this->t('Secret key'),
      '#description'   => $this->t('Select the Key entity that holds your Rapyd secret key.'),
      '#default_value' => $this->configuration['secret_key_id'],
      '#empty_option'  => $this->t('- Select a key -'),
      '#required'      => TRUE,
    ];

    $form['currency'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Currency code'),
      '#description'   => $this->t('ISO 4217 currency code supported by your Rapyd account, e.g. USD, EUR, ISK.'),
      '#default_value' => $this->configuration['currency'],
      '#required'      => TRUE,
      '#size'          => 6,
      '#maxlength'     => 3,
    ];

    $form['country'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Country code'),
      '#description'   => $this->t('ISO 3166-1 alpha-2 country code for your Rapyd account, e.g. US, DE, IS.'),
      '#default_value' => $this->configuration['country'],
      '#required'      => TRUE,
      '#size'          => 4,
      '#maxlength'     => 2,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);
    $this->configuration['access_key_id'] = $values['access_key_id'];
    $this->configuration['secret_key_id'] = $values['secret_key_id'];
    $this->configuration['currency']      = strtoupper(trim($values['currency']));
    $this->configuration['country']       = strtoupper(trim($values['country']));
  }

  /**
   * Builds a configured RapydClient instance using stored key IDs.
   *
   * @return \Drupal\rapyd_checkout\RapydClient
   *   The configured Rapyd API client.
   */
  public function getRapydClient(): RapydClient {
    $access_key = $this->keyRepository->getKey($this->configuration['access_key_id'])?->getKeyValue() ?? '';
    $secret_key = $this->keyRepository->getKey($this->configuration['secret_key_id'])?->getKeyValue() ?? '';
    $sandbox    = $this->getMode() === 'test';

    return new RapydClient($access_key, $secret_key, $sandbox, $this->httpClient, $this->logger);
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request): void {
    $checkout_id = $order->getData('rapyd_checkout_id');
    if (!$checkout_id) {
      throw new PaymentGatewayException('Missing checkout ID for order.');
    }

    $client = $this->getRapydClient();
    try {
      $data = $client->getCheckoutStatus((string) $checkout_id);
    }
    catch (\RuntimeException $e) {
      throw new PaymentGatewayException('Could not verify payment: ' . $e->getMessage(), 0, $e);
    }

    $payment_status  = $data['payment']['status'] ?? '';
    $checkout_status = $data['status'] ?? '';
    if ($payment_status !== 'CLO' && $checkout_status !== 'DON') {
      throw new PaymentGatewayException('Payment not completed (checkout: ' . $checkout_status . ', payment: ' . $payment_status . ').');
    }

    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

    $remote_id = $data['payment']['id'] ?? $data['id'] ?? $checkout_id;

    $existing = $payment_storage->loadByProperties([
      'order_id'  => $order->id(),
      'remote_id' => $remote_id,
    ]);
    if ($existing) {
      return;
    }

    $payment = $payment_storage->create([
      'state'           => 'completed',
      'amount'          => $order->getTotalPrice(),
      'payment_gateway' => $this->parentEntity->id(),
      'order_id'        => $order->id(),
      'remote_id'       => $remote_id,
    ]);
    $payment->save();

    $order->getState()->applyTransitionById('place');
    $order->save();
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request): Response {
    $raw_body  = $request->getContent();
    $salt      = $request->headers->get('rapyd-idempotency', '');
    $timestamp = $request->headers->get('rapyd-timestamp', '');
    $signature = $request->headers->get('rapyd-signature', '');
    $path      = $request->getPathInfo();

    $client = $this->getRapydClient();
    if (!$client->verifyWebhook($raw_body, $salt, $timestamp, $signature, $path)) {
      $this->logger->warning('Rapyd webhook: invalid signature rejected.');
      return new Response('Invalid signature', 401);
    }

    $payload = json_decode($raw_body, TRUE);
    if (($payload['type'] ?? '') !== 'PAYMENT_COMPLETED') {
      return new Response('', 200);
    }

    $ref = $payload['data']['merchant_reference_id'] ?? '';
    if (!preg_match('/^rapyd-order-(\d+)$/', $ref, $m)) {
      $this->logger->warning('Rapyd webhook: unrecognised merchant_reference_id "@ref".', ['@ref' => $ref]);
      return new Response('', 200);
    }
    $order_id  = (int) $m[1];
    $remote_id = $payload['data']['id'] ?? '';

    $order = $this->entityTypeManager->getStorage('commerce_order')->load($order_id);
    if (!$order) {
      $this->logger->error('Rapyd webhook: order @id not found.', ['@id' => $order_id]);
      return new Response('', 200);
    }

    $existing = $this->entityTypeManager->getStorage('commerce_payment')->loadByProperties([
      'order_id'  => $order_id,
      'remote_id' => $remote_id,
    ]);
    if ($existing || $order->getState()->getId() === 'completed') {
      return new Response('', 200);
    }

    $payment = $this->entityTypeManager->getStorage('commerce_payment')->create([
      'state'           => 'completed',
      'amount'          => $order->getTotalPrice(),
      'payment_gateway' => $this->parentEntity->id(),
      'order_id'        => $order_id,
      'remote_id'       => $remote_id,
    ]);
    $payment->save();

    $order->getState()->applyTransitionById('place');
    $order->save();

    return new Response('', 200);
  }

}
