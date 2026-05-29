<?php

namespace Drupal\giftcard_core;

/**
 * No-op payment client used as a fallback when no gateway module is installed.
 *
 * Install a gateway sub-module (e.g. giftcard_rapyd) to replace this service
 * with a real implementation.
 */
class NullPaymentClient implements PaymentClientInterface {

  /**
   * {@inheritdoc}
   */
  public function createCheckout(int $amount, string $currency, string $country, string $completeUrl, string $cancelUrl): ?array {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function verifyWebhookSignature(string $body, string $salt, string $timestamp, string $signature, string $path): bool {
    return FALSE;
  }

}
