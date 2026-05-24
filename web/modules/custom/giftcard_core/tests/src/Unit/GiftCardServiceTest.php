<?php

namespace Drupal\Tests\giftcard_core\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Tests\UnitTestCase;
use Drupal\giftcard_core\GiftCardInterface;
use Drupal\giftcard_core\GiftCardService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(GiftCardService::class)]
#[Group('giftcard_core')]
class GiftCardServiceTest extends UnitTestCase {

  private function makeService(): GiftCardService {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturn($storage);

    return new GiftCardService(
      $entityTypeManager,
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(LoggerChannelFactoryInterface::class),
      $this->createMock(PrivateTempStoreFactory::class),
      $this->createMock(KeyValueExpirableFactoryInterface::class),
    );
  }

  public function testGenerateCodeReturnsSixteenCharacters(): void {
    $code = $this->makeService()->generateCode();
    $this->assertSame(16, strlen($code));
  }

  public function testGenerateCodeIsUppercaseAlphanumeric(): void {
    $code = $this->makeService()->generateCode();
    $this->assertMatchesRegularExpression('/^[A-Z0-9]{16}$/', $code);
  }

  public function testGenerateCodeReturnsDifferentValuesEachCall(): void {
    $service = $this->makeService();
    $codes   = array_map(fn() => $service->generateCode(), range(1, 10));
    $this->assertGreaterThan(1, count(array_unique($codes)));
  }

  public function testCreateGiftCardPreventsDuplicates(): void {
    $existingCard = $this->createMock(GiftCardInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')
      ->with(['rapyd_payment_id' => 'pay_abc123'])
      ->willReturn([$existingCard]);
    $storage->expects($this->never())->method('create');

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturn($storage);

    $service = new GiftCardService(
      $entityTypeManager,
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(LoggerChannelFactoryInterface::class),
      $this->createMock(PrivateTempStoreFactory::class),
      $this->createMock(KeyValueExpirableFactoryInterface::class),
    );

    $result = $service->createGiftCard(['rapyd_payment_id' => 'pay_abc123']);
    $this->assertSame($existingCard, $result);
  }

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

    $service = new GiftCardService(
      $entityTypeManager,
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(LoggerChannelFactoryInterface::class),
      $tempStoreFactory,
      $this->createMock(KeyValueExpirableFactoryInterface::class),
    );

    $service->storeCheckoutData($data);
    $this->assertSame($data, $service->getCheckoutData());
  }

}
