<?php

declare(strict_types=1);

namespace SilverShop\ClickAndCollect\Tests\Model;

use SilverShop\ClickAndCollect\Model\PickupShippingMethod;
use SilverShop\Model\Address;
use SilverShop\Model\Order;
use SilverShop\Shipping\ShippingPackage;
use SilverStripe\Dev\SapphireTest;

/**
 * The PickupShippingMethod contract: free (or flat-fee) rate, no address required, and always kept by the
 * estimator even with no address present.
 */
class PickupShippingMethodTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testRequiresAddressIsFalse(): void
    {
        $this->assertFalse(PickupShippingMethod::create()->requiresAddress());
    }

    public function testCalculateRateIsZeroByDefault(): void
    {
        $method = PickupShippingMethod::create(['Name' => 'Collect', 'Enabled' => 1]);
        $rate = $method->calculateRate(ShippingPackage::create(), Address::create());

        $this->assertNotNull($rate, 'Pickup must return a rate (not null), so the estimator keeps it');
        $this->assertSame(0.0, (float) $rate);
    }

    public function testCalculateRateHonoursConfiguredFee(): void
    {
        $method = PickupShippingMethod::create(['Name' => 'Collect', 'Enabled' => 1, 'Fee' => 2.5]);
        $rate = $method->calculateRate(ShippingPackage::create(), Address::create());

        $this->assertSame(2.5, (float) $rate);
    }

    public function testEstimatorKeepsPickupWithoutAddress(): void
    {
        $method = PickupShippingMethod::create(['Name' => 'Collect', 'Enabled' => 1]);
        $method->write();

        // An order with no items and no shipping address whatsoever.
        $order = Order::create();
        $order->write();

        $estimates = $order->getShippingEstimates();

        $this->assertContains(
            $method->ID,
            $estimates->column('ID'),
            'Pickup method should appear in the estimates even with no address'
        );
    }
}
