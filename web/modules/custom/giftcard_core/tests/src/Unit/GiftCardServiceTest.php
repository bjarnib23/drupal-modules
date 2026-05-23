<?php

namespace Drupal\Tests\giftcard_core\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\giftcard_core\GiftCardInterface;
use Drupal\giftcard_core\GiftCardService;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for GiftCardService.
 */
#[CoversClass(GiftCardService::class)]
#[Group('giftcard_core')]
class GiftCardServiceTest extends UnitTestCase {

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

  public function testCreateGiftCardCreatesAndSavesEntity(): void {
    $card = $this->createMock(GiftCardInterface::class);
    $card->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $storage->method('create')->willReturn($card);

    $service = $this->makeService($storage);

    $result = $service->createGiftCard([
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

  public function testCreateGiftCardReturnsDuplicateWithoutCreating(): void {
    $existing = $this->createMock(GiftCardInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')
      ->with(['rapyd_payment_id' => 'pay_dup'])
      ->willReturn([$existing]);
    $storage->expects($this->never())->method('create');

    $service = $this->makeService($storage);

    $result = $service->createGiftCard([
      'rapyd_payment_id' => 'pay_dup',
      'code'             => 'DUPCODE1234567X',
      'amount'           => 5000,
      'currency'         => 'ISK',
    ]);

    $this->assertSame($existing, $result);
  }

}
