<?php

namespace Drupal\giftcard_core;

/**
 * Interface for payment gateway clients.
 *
 * Implement this interface to add support for any payment provider
 * (e.g. Stripe, PayPal) without modifying the gift card module itself.
 */
interface PaymentClientInterface {

  /**
   * Creates a hosted checkout session and returns the redirect URL.
   *
   * @param int    $amount      Amount in the smallest currency unit.
   * @param string $currency    ISO 4217 currency code.
   * @param string $country     ISO 3166-1 alpha-2 country code.
   * @param string $completeUrl Absolute URL to redirect to after payment.
   * @param string $cancelUrl   Absolute URL to redirect to on cancellation.
   *
   * @return array{redirect_url: string, payment_id: string}|null
   *   Array with redirect URL and payment ID, or NULL on failure.
   */
  public function createCheckout(int $amount, string $currency, string $country, string $completeUrl, string $cancelUrl): ?array;

  /**
   * Verifies the signature of an inbound webhook request.
   *
   * Always call this before trusting any webhook payload.
   *
   * @param string $body      Raw request body.
   * @param string $signature Signature from the request header.
   *
   * @return bool TRUE if the signature is valid.
   */
  public function verifyWebhookSignature(string $body, string $signature): bool;

}
