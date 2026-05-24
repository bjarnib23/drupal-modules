<?php

namespace Drupal\Tests\giftcard_core\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\giftcard_core\RapydClient;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for RapydClient webhook signature verification.
 */
#[CoversClass(RapydClient::class)]
#[Group('giftcard_core')]
class RapydClientTest extends UnitTestCase {

  private function makeClient(string $access = 'acc', string $secret = 'sec'): RapydClient {
    $accessKey = $this->createMock(KeyInterface::class);
    $accessKey->method('getKeyValue')->willReturn($access);
    $secretKey = $this->createMock(KeyInterface::class);
    $secretKey->method('getKeyValue')->willReturn($secret);

    $keyRepo = $this->createMock(KeyRepositoryInterface::class);
    $keyRepo->method('getKey')->willReturnMap([
      ['access_id', $accessKey],
      ['secret_id', $secretKey],
    ]);

    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturnMap([
      ['rapyd_access_key_id', 'access_id'],
      ['rapyd_secret_key_id', 'secret_id'],
      ['rapyd_sandbox', TRUE],
    ]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($settings);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    return new RapydClient(
      $this->createMock(ClientInterface::class),
      $keyRepo,
      $configFactory,
      $loggerFactory,
      $this->createMock(LanguageManagerInterface::class),
    );
  }

  private function makeEmptyKeysClient(): RapydClient {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturnMap([
      ['rapyd_access_key_id', ''],
      ['rapyd_secret_key_id', ''],
      ['rapyd_sandbox', TRUE],
    ]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($settings);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    return new RapydClient(
      $this->createMock(ClientInterface::class),
      $this->createMock(KeyRepositoryInterface::class),
      $configFactory,
      $loggerFactory,
      $this->createMock(LanguageManagerInterface::class),
    );
  }

  public function testVerifyWebhookReturnsFalseWithEmptyKeys(): void {
    $client = $this->makeEmptyKeysClient();
    $this->assertFalse($client->verifyWebhookSignature('body', 'salt', '123', 'sig', '/path'));
  }

  public function testVerifyWebhookValidSignature(): void {
    $access    = 'my_access';
    $secret    = 'my_secret';
    $raw_body  = '{"type":"PAYMENT_COMPLETED"}';
    $salt      = 'abcdef12';
    $timestamp = '1700000000';
    $path      = '/gift-card/webhook';

    $toSign    = 'post' . $path . $salt . $timestamp . $access . $secret . $raw_body;
    $signature = base64_encode(hash_hmac('sha256', $toSign, $secret));

    $client = $this->makeClient($access, $secret);
    $this->assertTrue($client->verifyWebhookSignature($raw_body, $salt, $timestamp, $signature, $path));
  }

  public function testVerifyWebhookInvalidSignature(): void {
    $client = $this->makeClient('acc', 'sec');
    $this->assertFalse($client->verifyWebhookSignature('body', 'salt', '123', 'wrong_sig', '/path'));
  }

}
