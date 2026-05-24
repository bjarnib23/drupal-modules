<?php

namespace Drupal\Tests\giftcard_core\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\giftcard_core\Entity\GiftCard;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the GiftCard entity can be created, saved, and loaded.
 *
 * @group giftcard_core
 */
#[RunTestsInSeparateProcesses]
class GiftCardEntityTest extends EntityKernelTestBase {

  protected static $modules = ['giftcard_core', 'key'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('gift_card');
    $this->installConfig(['giftcard_core']);
  }

  public function testGiftCardCanBeCreatedAndLoaded(): void {
    $giftCard = GiftCard::create([
      'code'            => 'TESTCODE12345678',
      'recipient_name'  => 'Anna Sigurðardóttir',
      'recipient_email' => 'anna@example.com',
      'sender_name'     => 'Jón Jónsson',
      'sender_email'    => 'jon@example.com',
      'amount'          => 5000,
      'currency'        => 'ISK',
      'message'         => 'Til hamingju með afmælið!',
      'status'          => 'active',
    ]);

    $this->assertSame(SAVED_NEW, $giftCard->save());
    $this->assertNotEmpty($giftCard->id());

    $loaded = GiftCard::load($giftCard->id());
    $this->assertSame('TESTCODE12345678', $loaded->getCode());
    $this->assertSame('Anna Sigurðardóttir', $loaded->getRecipientName());
    $this->assertSame('anna@example.com', $loaded->getRecipientEmail());
    $this->assertSame(5000, $loaded->getAmount());
    $this->assertSame('ISK', $loaded->getCurrency());
    $this->assertSame('active', $loaded->getStatus());
  }

  public function testGiftCardRequiresCode(): void {
    $giftCard = GiftCard::create([
      'recipient_name'  => 'Anna Sigurðardóttir',
      'recipient_email' => 'anna@example.com',
      'sender_name'     => 'Jón Jónsson',
      'sender_email'    => 'jon@example.com',
      'amount'          => 5000,
      'currency'        => 'ISK',
      'status'          => 'active',
    ]);

    $violations = $giftCard->validate();
    $this->assertGreaterThan(0, $violations->count());
  }

}
