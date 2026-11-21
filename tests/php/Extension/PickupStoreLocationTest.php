<?php

declare(strict_types=1);

namespace SilverShop\ClickAndCollect\Tests\Extension;

use SilverShop\ClickAndCollect\Email\ReadyForPickupEmail;
use SilverShop\ClickAndCollect\Model\PickupShippingMethod;
use SilverShop\Model\Address;
use SilverShop\Model\Order;
use SilverShop\Shipping\ShippingPackage;
use SilverShop\StoreLocations\Forms\WeeklyHoursField;
use SilverShop\StoreLocations\Model\StoreLocation;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\i18n\i18n;

/**
 * The store-locations link: one pickup method per store, shown on the order and in the pickup email.
 */
class PickupStoreLocationTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(StoreLocation::class)) {
            $this->markTestSkipped('silvershop-suite/store-locations is not installed.');
        }
        i18n::set_locale('en_GB');
    }

    private function store(array $data = []): StoreLocation
    {
        $location = StoreLocation::create(array_merge([
            'Title' => 'Voorstraat',
            'Street' => 'Voorstraat',
            'HouseNumber' => '1',
            'PostalCode' => '2611 JA',
            'City' => 'Delft',
            'Timezone' => 'Europe/Amsterdam',
            'RegularHours' => WeeklyHoursField::encode([1 => '10:00-18:00', 2 => '10:00-18:00', 6 => '10:00-17:00']),
        ], $data));
        $location->write();

        return $location;
    }

    private function pickupOrder(StoreLocation $location): Order
    {
        $method = PickupShippingMethod::create([
            'Name' => 'Collect at ' . $location->Title,
            'Enabled' => 1,
            'StoreLocationID' => $location->ID,
        ]);
        $method->write();

        $order = Order::create(['Email' => 'customer@example.test']);
        $order->write();
        $order->setShippingMethod($method);
        $order->write();

        return $order;
    }

    public function testOrderResolvesTheStoreOfItsPickupMethod(): void
    {
        $location = $this->store();
        $order = $this->pickupOrder($location);

        $this->assertSame($location->ID, $order->PickupStoreLocation()?->ID);

        $summary = $order->PickupLocationSummary();
        $this->assertStringContainsString('<strong>Voorstraat</strong>', $summary);
        $this->assertStringContainsString('2611 JA Delft', $summary);
    }

    public function testNonPickupOrderHasNoStore(): void
    {
        $order = Order::create();
        $order->write();

        $this->assertNull($order->PickupStoreLocation());
    }

    public function testPickupEmailUsesTheStoreAndItsHours(): void
    {
        $order = $this->pickupOrder($this->store());
        $email = ReadyForPickupEmail::create_for($order);
        $data = $email->getData();

        $this->assertSame('Voorstraat', $data->StoreName);
        $this->assertStringContainsString('2611 JA Delft', (string) $data->StoreAddress);
        $this->assertNotNull($data->StoreHours);
        $this->assertSame('Mon to Tue', $data->StoreHours->first()->Days);

        $html = (string) $data->renderWith($email->getHTMLTemplate());
        $this->assertStringContainsString('Opening hours', $html);
        $this->assertStringContainsString('Mon to Tue: 10:00 – 18:00', $html);
    }

    public function testMethodOfAPermanentlyClosedStoreIsNotOffered(): void
    {
        $open = PickupShippingMethod::create(['Name' => 'Open', 'Fee' => 0, 'StoreLocationID' => $this->store()->ID]);
        $closed = PickupShippingMethod::create([
            'Name' => 'Closed',
            'Fee' => 0,
            'StoreLocationID' => $this->store(['Title' => 'Old shop', 'Status' => StoreLocation::STATUS_PERMANENTLY_CLOSED])->ID,
        ]);

        $package = ShippingPackage::create();
        $address = Address::create();

        $this->assertSame(0.0, $open->calculateRate($package, $address));
        $this->assertNull($closed->calculateRate($package, $address));
    }
}
