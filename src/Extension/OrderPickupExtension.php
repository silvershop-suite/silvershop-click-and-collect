<?php

declare(strict_types=1);

namespace SilverShop\ClickAndCollect\Extension;

use SilverShop\ClickAndCollect\Email\ReadyForPickupEmail;
use SilverShop\ClickAndCollect\Model\PickupShippingMethod;
use SilverShop\Model\Order;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Adds the "collect" fulfilment layer to an {@link Order}: the two pickup lifecycle datetimes
 * (ReadyForPickup / Collected), the {@link IsPickup()} predicate that drives address suppression and the
 * pickup copy, the standalone admin Pickup panel, and the transition that fires the ready-for-pickup email.
 *
 * Fulfilment is tracked on datetimes (like the core Dispatched / Printed pattern the order-admin screen
 * already reads), not on a new Order.Status enum value — so it coexists with the existing status machinery.
 *
 * @property ?string $ReadyForPickup
 * @property ?string $Collected
 * @extends Extension<Order>
 */
class OrderPickupExtension extends Extension
{
    private static array $db = [
        'ReadyForPickup' => 'Datetime',
        'Collected' => 'Datetime',
    ];

    /**
     * When true, {@link markCollected()} also advances the order Status to Complete. Off by default: v1 keeps
     * fulfilment on the datetimes and leaves the payment / status machinery untouched.
     */
    private static bool $complete_on_collected = false;

    /**
     * Pending ready-for-pickup sends, keyed by {@see spl_object_id()} of the owning order for the duration of
     * a single write() (captured in {@link onBeforeWrite()}, consumed in {@link onAfterWrite()}). Extension
     * instances are reused across owners, so this must never be a bare scalar.
     *
     * @var array<int, bool>
     */
    private array $pendingReadyEmail = [];

    /**
     * True when the order is being collected in store (its chosen shipping method is a PickupShippingMethod).
     */
    public function IsPickup(): bool
    {
        $method = $this->getOwner()->ShippingMethod();

        return $method !== null && $method->exists() && $method instanceof PickupShippingMethod;
    }

    public function IsReadyForPickup(): bool
    {
        return (bool) $this->getOwner()->ReadyForPickup && !$this->getOwner()->Collected;
    }

    public function IsCollected(): bool
    {
        return (bool) $this->getOwner()->Collected;
    }

    /**
     * Derived pickup fulfilment state: "collected" | "ready" | "awaiting". Only meaningful for a pickup order.
     */
    public function PickupFulfilmentState(): string
    {
        if ($this->getOwner()->Collected) {
            return 'collected';
        }
        if ($this->getOwner()->ReadyForPickup) {
            return 'ready';
        }

        return 'awaiting';
    }

    /**
     * Stamp the order ready for collection. Idempotent: a no-op (returns false) when already ready. The write
     * triggers the empty->set transition that sends the ready-for-pickup email exactly once.
     */
    public function markReadyForPickup(): bool
    {
        $order = $this->getOwner();
        if ($order->ReadyForPickup) {
            return false;
        }

        $order->ReadyForPickup = DBDatetime::now()->Rfc2822();
        $order->write();

        return true;
    }

    /**
     * Stamp the order collected. Idempotent. When complete_on_collected is on, also advances Status to Complete.
     */
    public function markCollected(): bool
    {
        $order = $this->getOwner();
        if ($order->Collected) {
            return false;
        }

        $order->Collected = DBDatetime::now()->Rfc2822();
        // The extension's config static is merged onto the owning Order's config namespace.
        if ($order->config()->get('complete_on_collected') && $order->Status !== 'Complete') {
            $order->Status = 'Complete';
        }
        $order->write();

        return true;
    }

    /**
     * Capture an empty->set ReadyForPickup transition before the write, so {@link onAfterWrite()} can fire the
     * email exactly once (whether set via {@link markReadyForPickup()} or by editing the CMS field directly).
     */
    public function onBeforeWrite(): void
    {
        $order = $this->getOwner();
        $changed = $order->getChangedFields(['ReadyForPickup'], DataObject::CHANGE_VALUE);
        if (!isset($changed['ReadyForPickup'])) {
            return;
        }

        $before = $changed['ReadyForPickup']['before'] ?? null;
        $after = $changed['ReadyForPickup']['after'] ?? null;
        if (empty($before) && !empty($after)) {
            $this->pendingReadyEmail[spl_object_id($order)] = true;
        }
    }

    public function onAfterWrite(): void
    {
        $order = $this->getOwner();
        $key = spl_object_id($order);
        if (empty($this->pendingReadyEmail[$key])) {
            return;
        }

        unset($this->pendingReadyEmail[$key]);
        ReadyForPickupEmail::send_for($order);
    }

    public function updateCMSFields(FieldList $fields): void
    {
        $order = $this->getOwner();
        if (!$order->isInDB()) {
            return;
        }

        $fields->findOrMakeTab('Root.Pickup', _t(self::class . '.TabPickup', 'Pickup'));

        $location = $this->PickupLocationSummary();
        if ($location !== '') {
            $fields->addFieldToTab('Root.Pickup', LiteralField::create(
                'PickupLocation',
                '<div class="field"><label class="left">'
                . Convert::raw2xml(_t(self::class . '.Location', 'Pickup location'))
                . '</label><div class="middleColumn"><p class="form__field-description">'
                . $location
                . '</p></div></div>'
            ));
        }

        $fields->addFieldsToTab('Root.Pickup', [
            DatetimeField::create('ReadyForPickup', _t(self::class . '.ReadyForPickup', 'Ready for pickup'))
                ->setDescription(_t(
                    self::class . '.ReadyForPickupDesc',
                    'Set this to notify the customer their order is ready to collect. Saving emails them once.'
                )),
            DatetimeField::create('Collected', _t(self::class . '.Collected', 'Collected'))
                ->setDescription(_t(self::class . '.CollectedDesc', 'Set when the customer has collected the order.')),
        ]);
    }

    /**
     * The single implicit v1 pickup location: the store-profile address when that module is installed.
     * Returns a safe HTML fragment (field values escaped by the address formatter / {@see Convert::raw2xml()})
     * or an empty string.
     */
    public function PickupLocationSummary(): string
    {
        $config = SiteConfig::current_site_config();
        if (!$config->hasMethod('hasStoreAddress') || !$config->hasStoreAddress()) {
            return '';
        }

        $name = $config->hasMethod('StoreProfileName') ? (string) $config->StoreProfileName() : '';
        $address = $config->hasMethod('StoreFormattedAddress') ? (string) $config->StoreFormattedAddress(true) : '';

        return trim(($name !== '' ? '<strong>' . Convert::raw2xml($name) . '</strong><br>' : '') . $address);
    }
}
