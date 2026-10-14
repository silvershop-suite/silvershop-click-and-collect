<?php

declare(strict_types=1);

namespace SilverShop\ClickAndCollect\Tests\Extension;

use SilverShop\ClickAndCollect\Model\PickupShippingMethod;
use SilverShop\Model\Order;
use SilverStripe\Dev\SapphireTest;

/**
 * Address suppression on pickup, plus the ReadyForPickup / Collected fulfilment transitions.
 */
class OrderPickupExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testAddressSuppressedForPickup(): void
    {
        $method = PickupShippingMethod::create(['Name' => 'Collect', 'Enabled' => 1]);
        $method->write();

        // No shipping address set at all.
        $order = Order::create();
        $order->write();

        $result = $order->setShippingMethod($method);

        $this->assertTrue($result, 'setShippingMethod must accept a pickup method with no address');
        $this->assertSame($method->ID, (int) $order->ShippingMethodID);
        $this->assertSame(0.0, (float) $order->ShippingTotal);
        $this->assertTrue($order->IsPickup());
    }

    public function testIsPickupFalseForNoMethod(): void
    {
        $order = Order::create();
        $order->write();

        $this->assertFalse($order->IsPickup());
    }

    public function testReadyForPickupTransition(): void
    {
        $order = Order::create();
        $order->write();

        $this->assertSame('awaiting', $order->PickupFulfilmentState());
        $this->assertFalse($order->IsReadyForPickup());

        $this->assertTrue($order->markReadyForPickup());
        $this->assertNotEmpty($order->ReadyForPickup);
        $this->assertTrue($order->IsReadyForPickup());
        $this->assertSame('ready', $order->PickupFulfilmentState());

        // Idempotent — a second call is a no-op.
        $this->assertFalse($order->markReadyForPickup());
    }

    public function testCollectedTransition(): void
    {
        $order = Order::create();
        $order->write();

        $this->assertTrue($order->markCollected());
        $this->assertNotEmpty($order->Collected);
        $this->assertTrue($order->IsCollected());
        $this->assertSame('collected', $order->PickupFulfilmentState());

        // Idempotent.
        $this->assertFalse($order->markCollected());
    }

    public function testPickupTabAddedToCMSFields(): void
    {
        $order = Order::create(['Email' => 'buyer@example.com']);
        $order->write();

        $fields = $order->getCMSFields();

        $this->assertNotNull($fields->fieldByName('Root.Pickup'), 'A Pickup tab is added to the order edit form');
        $this->assertNotNull($fields->dataFieldByName('ReadyForPickup'));
        $this->assertNotNull($fields->dataFieldByName('Collected'));
    }

    public function testReadyThenCollectedStateProgression(): void
    {
        $order = Order::create();
        $order->write();

        $order->markReadyForPickup();
        $this->assertSame('ready', $order->PickupFulfilmentState());

        $order->markCollected();
        $this->assertSame('collected', $order->PickupFulfilmentState());
        $this->assertFalse($order->IsReadyForPickup(), 'A collected order is no longer merely "ready"');
    }
}
