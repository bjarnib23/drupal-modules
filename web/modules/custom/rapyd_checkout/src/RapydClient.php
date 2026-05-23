<?php

namespace Drupal\rapyd_checkout;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * API client for the Rapyd Hosted Checkout API.
 */
class RapydClient {

  private string $baseUrl;

  public function __construct(
    private string $accessKey,
    private string $secretKey,
    bool $sandbox,
    private ClientInterface $http,
    private LoggerInterface $logger,
  ) {
    $this->baseUrl = $sandbox
      ? 'https://sandboxapi.rapyd.net'
      : 'https://api.rapyd.net';
  }

  /**
   * Creates a Rapyd Hosted Checkout page and returns the redirect URL.
   *
   * @return array{redirect_url: string, checkout_id: string}
   *
   * @throws \RuntimeException
   */
  public function createCheckout(int $order_id, int $amount, string $email, string $complete_url, string $error_url): array {
    if (empty($this->accessKey) || empty($this->secretKey)) {
      throw new \RuntimeException('Rapyd API credentials are not configured.');
    }

    $path     = '/v1/checkout';
    $body     = [
      'amount'                => $amount,
      'currency'              => 'ISK',
      'country'               => 'IS',
      'complete_payment_url'  => $complete_url,
      'error_payment_url'     => $error_url,
      'merchant_reference_id' => 'rapyd-order-' . $order_id,
      'metadata'              => ['order_id' => $order_id],
      'requested_by'          => $email,
    ];
    $body_str = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $headers  = $this->buildHeaders('post', $path, $body_str);

    try {
      $response = $this->http->request('POST', $this->baseUrl . $path, [
        'headers' => $headers,
        'body'    => $body_str,
      ]);
      $decoded = json_decode((string) $response->getBody(), TRUE);

      if (!is_array($decoded) || empty($decoded['data']['redirect_url'])) {
        throw new \RuntimeException('Rapyd response missing redirect_url.');
      }

      return [
        'redirect_url' => $decoded['data']['redirect_url'],
        'checkout_id'  => $decoded['data']['id'] ?? '',
      ];
    }
    catch (GuzzleException $e) {
      $this->logger->error('Rapyd createCheckout error: @msg', ['@msg' => $e->getMessage()]);
      throw new \RuntimeException('Rapyd API request failed: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * Fetches the status of a checkout page by ID.
   *
   * @return array Decoded Rapyd checkout data.
   *
   * @throws \RuntimeException
   */
  public function getCheckoutStatus(string $checkout_id): array {
    $path    = '/v1/checkout/' . $checkout_id;
    $headers = $this->buildHeaders('get', $path);

    try {
      $response = $this->http->request('GET', $this->baseUrl . $path, [
        'headers' => $headers,
      ]);
      $decoded = json_decode((string) $response->getBody(), TRUE);
      return $decoded['data'] ?? [];
    }
    catch (GuzzleException $e) {
      $this->logger->error('Rapyd getCheckoutStatus error: @msg', ['@msg' => $e->getMessage()]);
      throw new \RuntimeException('Could not fetch checkout status: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * Verifies an incoming Rapyd webhook signature.
   *
   * Signature is always verified regardless of sandbox/live mode.
   */
  public function verifyWebhook(string $raw_body, string $salt, string $timestamp, string $signature, string $path): bool {
    if (empty($this->secretKey) || empty($this->accessKey)) {
      return FALSE;
    }
    $to_sign  = 'post' . $path . $salt . $timestamp . $this->accessKey . $this->secretKey . $raw_body;
    $expected = base64_encode(hash_hmac('sha256', $to_sign, $this->secretKey));
    return hash_equals($expected, $signature);
  }

  private function buildHeaders(string $method, string $path, string $body_str = ''): array {
    $salt      = bin2hex(random_bytes(8));
    $timestamp = (string) time();
    $to_sign   = strtolower($method) . $path . $salt . $timestamp . $this->accessKey . $this->secretKey . $body_str;
    $signature = base64_encode(hash_hmac('sha256', $to_sign, $this->secretKey));

    return [
      'access_key'   => $this->accessKey,
      'salt'         => $salt,
      'timestamp'    => $timestamp,
      'signature'    => $signature,
      'Content-Type' => 'application/json',
    ];
  }

}
