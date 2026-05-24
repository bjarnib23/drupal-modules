<?php

namespace Drupal\Tests\giftcard_core\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Tests\UnitTestCase;
use Drupal\giftcard_core\GiftCardInterface;
use Drupal\giftcard_core\GiftCardService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for GiftCardService.
 */
#[CoversClass(GiftCardService::class)]
#[Group('giftcard_core')]
class GiftCardServiceTest extends UnitTestCase {

  /**
   * Builds a GiftCardService with mocked dependencies.
   */
  private function makeService(EntityStorageInterface $storage): GiftCardService {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('gift_card')->willReturn($storage);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    return new GiftCardService(
      $entityTypeManager,
      $this->createMock(ConfigFactoryInterface::class),
      $loggerFactory,
      $this->createMock(PrivateTempStoreFactory::class),
      $this->createMock(KeyValueExpirableFactoryInterface::class),
    );
  }

  /**
   * Tests that generateCode returns a 16-character string.
   */
  public function testGenerateCodeReturnsSixteenCharacters(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $code = $this->makeService($storage)->generateCode();
    $this->assertSame(16, strlen($code));
  }

  /**
   * Tests that generateCode returns an uppercase alphanumeric string.
   */
  public function testGenerateCodeIsUppercaseAlphanumeric(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $code = $this->makeService($storage)->generateCode();
    $this->assertMatchesRegularExpression('/^[A-Z0-9]{16}$/', $code);
  }

  /**
   * Tests that generateCode produces unique values across multiple calls.
   */
  public function testGenerateCodeReturnsDifferentValuesEachCall(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $service = $this->makeService($storage);
    $codes   = array_map(fn() => $service->generateCode(), range(1, 10));
    $this->assertGreaterThan(1, count(array_unique($codes)));
  }

  /**
   * Tests that createGiftCard creates and saves a new gift card entity.
   */
  public function testCreateGiftCardCreatesAndSavesEntity(): void {
    $card = $this->createMock(GiftCardInterface::class);
    $card->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $storage->method('create')->willReturn($card);

    $result = $this->makeService($storage)->createGiftCard([
      'rapyd_payment_id' => 'pay_new',
      'code'             => 'TESTCODE1234567',
      'recipient_name'   => 'Bob',
      'recipient_email'  => 'bob@example.com',
      'sender_name'      => 'Alice',
      'sender_email'     => 'alice@example.com',
      'amount'           => 5000,
      'currency'         => 'ISK',
    ]);

    $this->assertSame($card, $result);
  }

  /**
   * Tests that createGiftCard returns existing entity on duplicate payment ID.
   */
  public function testCreateGiftCardPreventsDuplicates(): void {
    $existing = $this->createMock(GiftCardInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')
      ->with(['rapyd_payment_id' => 'pay_dup'])
      ->willReturn([$existing]);
    $storage->expects($this->never())->method('create');

    $result = $this->makeService($storage)->createGiftCard([
      'rapyd_payment_id' => 'pay_dup',
      'amount'           => 5000,
      'currency'         => 'ISK',
    ]);

    $this->assertSame($existing, $result);
  }

  /**
   * Tests that checkout data can be stored and retrieved from the temp store.
   */
  public function testStoreAndRetrieveCheckoutData(): void {
    $data  = ['sender_name' => 'Jón', 'amount' => 5000];
    $store = $this->createMock(PrivateTempStore::class);
    $store->expects($this->once())->method('set')->with('checkout_data', $data);
    $store->expects($this->once())->method('get')->with('checkout_data')->willReturn($data);

    $tempStoreFactory = $this->createMock(PrivateTempStoreFactory::class);
    $tempStoreFactory->method('get')->willReturn($store);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturn($storage);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    $service = new GiftCardService(
      $entityTypeManager,
      $this->createMock(ConfigFactoryInterface::class),
      $loggerFactory,
      $tempStoreFactory,
      $this->createMock(KeyValueExpirableFactoryInterface::class),
    );

    $service->storeCheckoutData($data);
    $this->assertSame($data, $service->getCheckoutData());
  }

}
