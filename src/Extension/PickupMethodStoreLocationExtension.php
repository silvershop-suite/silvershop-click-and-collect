<?php

declare(strict_types=1);

namespace SilverShop\ClickAndCollect\Extension;

use SilverShop\ClickAndCollect\Model\PickupShippingMethod;
use SilverShop\StoreLocations\Model\StoreLocation;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;

/**
 * Links a pickup method to a physical store from silvershop-suite/store-locations, so a shop with several stores
 * gets one pickup method per store ("Collect at High Street", "Collect at Riverside"). The order, the CMS and the
 * ready-for-pickup email then show that store's name, address and opening hours, and a method whose store is
 * permanently closed is no longer offered.
 *
 * Applied only when store-locations is installed (see _config/click-and-collect.yml); without it a pickup method
 * keeps using the single store-profile address.
 *
 * @property int $StoreLocationID
 * @method StoreLocation StoreLocation()
 * @extends Extension<PickupShippingMethod>
 */
class PickupMethodStoreLocationExtension extends Extension
{
    private static array $has_one = [
        'StoreLocation' => StoreLocation::class,
    ];

    /**
     * The linked store, or null when none is set.
     */
    public function PickupStoreLocation(): ?StoreLocation
    {
        $location = $this->getOwner()->StoreLocation();

        return $location->exists() ? $location : null;
    }

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->dataFieldByName('StoreLocationID')?->setDescription(_t(
            self::class . '.StoreLocationDesc',
            'The store where customers collect orders placed with this method. Its address and opening hours are '
            . 'shown on the order and in the pickup email.'
        ));
    }
}
