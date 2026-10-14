<?php

declare(strict_types=1);

namespace SilverShop\ClickAndCollect\Tests\Email;

use SilverShop\ClickAndCollect\Email\ReadyForPickupEmail;
use SilverShop\Model\Order;
use SilverStripe\Control\Email\Email;
use SilverStripe\Dev\SapphireTest;

/**
 * The ready-for-pickup email: recipient, the stamped notification-key / order-id headers the log reads, and
 * the best-effort send behaviour.
 */
class ReadyForPickupEmailTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        Email::config()->set('admin_email', 'shop@example.com');
    }

    public function testCreateForStampsNotificationHeaders(): void
    {
        $order = Order::create(['Email' => 'buyer@example.com']);
        $order->write();

        $email = ReadyForPickupEmail::create_for($order);
        $headers = $email->getHeaders();

        $this->assertTrue($headers->has('X-SilverShop-Notification-Key'));
        $this->assertSame('pickup.ready', $headers->get('X-SilverShop-Notification-Key')->getBodyAsString());
        $this->assertSame((string) $order->ID, $headers->get('X-SilverShop-Order-ID')->getBodyAsString());

        $to = $email->getTo();
        $this->assertNotEmpty($to);
        $this->assertSame('buyer@example.com', $to[0]->getAddress());
    }

    public function testSendForRecordsViaMailer(): void
    {
        $order = Order::create(['Email' => 'buyer@example.com']);
        $order->write();

        $this->assertTrue(ReadyForPickupEmail::send_for($order));
        $this->assertEmailSent('buyer@example.com');
    }

    public function testSendForNoRecipientReturnsFalse(): void
    {
        $order = Order::create();
        $order->write();

        $this->assertFalse(ReadyForPickupEmail::send_for($order), 'No recipient means nothing is sent');
    }

    public function testSendForDoesNotThrowWithNoConfiguredSender(): void
    {
        // No ShopConfig.email_from and no Email.admin_email: building the email must still not throw
        // (empty From would break the order write that triggers it) — it falls back to no-reply@host.
        Email::config()->set('admin_email', null);

        $order = Order::create(['Email' => 'buyer@example.com']);
        $order->write();

        $this->assertTrue(ReadyForPickupEmail::send_for($order));
        $this->assertEmailSent('buyer@example.com');
    }

    public function testMarkReadyForPickupSendsEmail(): void
    {
        $order = Order::create(['Email' => 'buyer@example.com']);
        $order->write();

        // The empty->set ReadyForPickup transition fires the email via the order write hook.
        $order->markReadyForPickup();

        $this->assertEmailSent('buyer@example.com');
    }
}
