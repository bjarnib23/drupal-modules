<?php

namespace Drupal\Tests\rapyd_checkout\Unit;

use Drupal\rapyd_checkout\RapydClient;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for RapydClient.
 */
#[CoversClass(RapydClient::class)]
#[Group('rapyd_checkout')]
class RapydClientTest extends UnitTestCase {

  private function makeClient(string $access = 'acc', string $secret = 'sec', bool $sandbox = TRUE): RapydClient {
    return new RapydClient(
      $access,
      $secret,
      $sandbox,
      $this->createMock(ClientInterface::class),
      $this->createMock(LoggerInterface::class),
    );
  }

  public function testVerifyWebhookReturnsFalseWithEmptyKeys(): void {
    $client = $this->makeClient('', '');
    $this->assertFalse($client->verifyWebhook('body', 'salt', '123', 'sig', '/path'));
  }

  public function testVerifyWebhookValidSignature(): void {
    $access    = 'my_access';
    $secret    = 'my_secret';
    $raw_body  = '{"type":"PAYMENT_COMPLETED"}';
    $salt      = 'abcdef12';
    $timestamp = '1700000000';
    $path      = '/payment/notify/rapyd_checkout';

    $to_sign   = 'post' . $path . $salt . $timestamp . $access . $secret . $raw_body;
    $signature = base64_encode(hash_hmac('sha256', $to_sign, $secret));

    $client = $this->makeClient($access, $secret);
    $this->assertTrue($client->verifyWebhook($raw_body, $salt, $timestamp, $signature, $path));
  }

  public function testVerifyWebhookInvalidSignature(): void {
    $client = $this->makeClient('acc', 'sec');
    $this->assertFalse($client->verifyWebhook('body', 'salt', '123', 'wrong_signature', '/path'));
  }

  public function testVerifyWebhookAlwaysVerifiesInSandboxMode(): void {
    // Sandbox mode must NOT bypass signature verification.
    $client = $this->makeClient('acc', 'sec', sandbox: TRUE);
    $this->assertFalse($client->verifyWebhook('body', 'salt', '123', 'bad_sig', '/path'));
  }

}
