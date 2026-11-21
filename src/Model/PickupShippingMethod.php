<?php

declare(strict_types=1);

namespace SilverShop\ClickAndCollect\Model;

use SilverShop\Model\Address;
use SilverShop\Shipping\Model\ShippingMethod;
use SilverShop\Shipping\ShippingPackage;
use SilverStripe\Forms\FieldList;

/**
 * A shipping method that represents "collect in store" rather than delivery.
 *
 * It is modelled as a {@link ShippingMethod} subclass so it drops straight into the existing shipping-method
 * selection surface (the checkout optionset + the {@link \SilverShop\Shipping\ShippingFrameworkModifier}
 * line) with no new checkout plumbing. Two behaviours set it apart from a delivery method:
 *
 *  - requiresAddress() returns false, so OrderShippingExtension::setShippingMethod() accepts it with no
 *    shipping address — the delivery address is suppressed for pickup orders.
 *  - calculateRate() always returns the configured Fee (0 by default), never null, so the ShippingEstimator
 *    keeps it even before an address is entered (it drops only methods whose rate is null).
 *
 * @property float $Fee
 */
class PickupShippingMethod extends ShippingMethod
{
    private static array $db = [
        'Fee' => 'Currency',
    ];

    private static string $table_name = 'SilverShop_PickupShippingMethod';

    private static string $singular_name = 'Pickup / collect method';

    private static string $plural_name = 'Pickup / collect methods';

    /**
     * Pickup never needs a delivery address — this is the hook
     * {@link \SilverShop\Shipping\Extension\OrderShippingExtension::setShippingMethod()} honours to let a
     * pickup order through checkout without one.
     */
    public function requiresAddress(): bool
    {
        return false;
    }

    /**
     * Pickup is free by default, or a flat configurable {@link $Fee}. Returns the fee (never null) regardless
     * of address, so the estimator always keeps the method.
     */
    public function calculateRate(ShippingPackage $package, Address $address): float|int|null
    {
        // A method linked to a permanently closed store (silvershop-suite/store-locations) is not offered:
        // returning null makes the shipping estimator drop it.
        $location = $this->hasMethod('PickupStoreLocation') ? $this->PickupStoreLocation() : null;
        if ($location !== null && !$location->isListed()) {
            return $this->CalculatedRate = null;
        }

        return $this->CalculatedRate = (float) $this->Fee;
    }

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        $fields->dataFieldByName('Fee')?->setDescription(
            _t(self::class . '.FeeDesc', 'Optional flat pickup fee. Leave at 0 for free collection.')
        );

        return $fields;
    }
}
