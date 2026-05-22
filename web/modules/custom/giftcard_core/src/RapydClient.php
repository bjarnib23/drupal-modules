<?php

namespace Drupal\giftcard_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Rapyd implementation of PaymentClientInterface.
 *
 * Credentials are loaded from the Key module. Every outbound request is
 * signed with HMAC-SHA256. Every inbound webhook signature is verified
 * before the payload is trusted.
 */
class RapydClient implements PaymentClientInterface {

  private const SANDBOX_BASE = 'https://sandboxapi.rapyd.net';
  private const LIVE_BASE    = 'https://api.rapyd.net';

  /**
   * Constructs a new RapydClient.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository for loading API credentials.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly KeyRepositoryInterface $keyRepository,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createCheckout(int $amount, string $currency, string $country, string $completeUrl, string $cancelUrl): ?array {
    $body = json_encode([
      'amount'                => $amount,
      'currency'              => $currency,
      'country'               => $country,
      'complete_checkout_url' => $completeUrl,
      'cancel_checkout_url'   => $cancelUrl,
      'language'              => $this->languageManager->getCurrentLanguage()->getId(),
    ]);

    $response = $this->request('POST', '/v1/checkout', $body);

    if ($response === NULL) {
      return NULL;
    }

    $redirect = $response['data']['redirect_url'] ?? NULL;
    $id       = $response['data']['id'] ?? NULL;

    if ($redirect === NULL || $id === NULL) {
      $this->loggerFactory->get('giftcard_core')->error('Rapyd checkout response missing redirect_url or id.');
      return NULL;
    }

    return ['redirect_url' => $redirect, 'payment_id' => $id];
  }

  /**
   * {@inheritdoc}
   */
  public function verifyWebhookSignature(string $body, string $signature): bool {
    $secret = $this->getSecret();
    if ($secret === NULL) {
      return FALSE;
    }

    $expected = base64_encode(hash_hmac('sha256', $body, $secret, TRUE));
    return hash_equals($expected, $signature);
  }

  /**
   * Sends a signed request to the Rapyd API.
   *
   * @param string $method
   *   The HTTP method (GET, POST, etc.).
   * @param string $path
   *   The API endpoint path.
   * @param string $body
   *   (optional) The JSON-encoded request body.
   *
   * @return array|null
   *   The decoded JSON response, or NULL on failure.
   */
  private function request(string $method, string $path, string $body = ''): ?array {
    $access = $this->getAccess();
    $secret = $this->getSecret();

    if ($access === NULL || $secret === NULL) {
      $this->loggerFactory->get('giftcard_core')->error('Rapyd API credentials are not configured.');
      return NULL;
    }

    $salt      = bin2hex(random_bytes(8));
    $timestamp = (string) time();
    $toSign    = strtolower($method) . $path . $salt . $timestamp . $access . $secret . $body;
    $signature = base64_encode(hash_hmac('sha256', $toSign, $secret));

    $headers = [
      'Content-Type' => 'application/json',
      'access_key'   => $access,
      'salt'         => $salt,
      'timestamp'    => $timestamp,
      'signature'    => $signature,
    ];

    try {
      $response = $this->httpClient->request($method, $this->baseUrl() . $path, [
        'headers' => $headers,
        'body'    => $body,
      ]);

      return json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (RequestException $e) {
      $this->loggerFactory->get('giftcard_core')->error(
        'Rapyd API request failed: @message',
        ['@message' => $e->getMessage()]
      );
      return NULL;
    }
  }

  /**
   * Returns the Rapyd API base URL based on sandbox setting.
   *
   * @return string
   *   The base URL.
   */
  private function baseUrl(): string {
    $sandbox = (bool) $this->configFactory->get('giftcard_core.settings')->get('rapyd_sandbox');
    return $sandbox ? self::SANDBOX_BASE : self::LIVE_BASE;
  }

  /**
   * Loads the Rapyd access key from the Key module.
   *
   * @return string|null
   *   The access key value, or NULL if not configured.
   */
  private function getAccess(): ?string {
    $keyId = $this->configFactory->get('giftcard_core.settings')->get('rapyd_access_key_id');
    if (empty($keyId)) {
      return NULL;
    }
    $key = $this->keyRepository->getKey($keyId);
    return $key ? $key->getKeyValue() : NULL;
  }

  /**
   * Loads the Rapyd secret key from the Key module.
   *
   * @return string|null
   *   The secret key value, or NULL if not configured.
   */
  private function getSecret(): ?string {
    $keyId = $this->configFactory->get('giftcard_core.settings')->get('rapyd_secret_key_id');
    if (empty($keyId)) {
      return NULL;
    }
    $key = $this->keyRepository->getKey($keyId);
    return $key ? $key->getKeyValue() : NULL;
  }

}
