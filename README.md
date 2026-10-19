# SilverShop Click & Collect

Local pickup / click & collect (BOPIS) for [SilverShop](https://github.com/silvershop/silvershop-core)
(CMS 6). Let a buyer choose **"Pick up in store"** instead of delivery: no shipping cost, no shipping
address required, and a first-class *collect* fulfilment layer the shop can act on
(ordered → ready for pickup → collected) with a styled **ready-for-pickup** notification email.

This is the **v1** slice: a single implicit pickup location (the store-profile address), €0 (or a flat fee)
collect, address suppression, the ready / collected fulfilment state, and the ready-for-pickup email.
See [Roadmap](#roadmap-v2) for what is deliberately out of scope.

## How it works

Pickup is modelled as a dedicated **`PickupShippingMethod`** — a `ShippingMethod` subclass — so it reuses
the existing shipping-method selection surface (the checkout optionset + the `ShippingFrameworkModifier`
line) with no new checkout plumbing:

- `requiresAddress()` returns **`false`**, so `OrderShippingExtension::setShippingMethod()` accepts a pickup
  order with **no shipping address** — the delivery address is suppressed.
- `calculateRate()` returns the configured **`Fee`** (0 by default), never `null`, so `ShippingEstimator`
  keeps the method even before an address is entered.

Behind the method, **`OrderPickupExtension`** adds the collect fulfilment layer to `Order`:

- `ReadyForPickup` and `Collected` datetimes (tracked like the core `Dispatched` / `Printed` pattern the
  order-admin screen already reads — no new `Order.Status` enum value);
- `IsPickup()`, `IsReadyForPickup()`, `IsCollected()`, `PickupFulfilmentState()` predicates;
- `markReadyForPickup()` / `markCollected()` actions, and a standalone **Pickup** tab on the order edit form
  (date fields + the store-profile pickup location) when `silvershop-suite/order-admin` is not driving it;
- when `ReadyForPickup` transitions from empty to set, a **ready-for-pickup email** is sent once.

The **`ReadyForPickupEmail`** is self-contained (its own inline-safe `<style>`, so the module installs on
core 6.1 without the 6.2 email shell) and stamps two headers an optional `silvershop-suite/notification-log`
records with zero coupling:

```
X-SilverShop-Order-ID: {OrderID}
X-SilverShop-Notification-Key: pickup.ready
```

## Installation

```bash
composer require silvershop-suite/click-and-collect
```

Then run `dev/build?flush=all`. In **Settings → Shipping methods** add a *Pickup / collect method*
(set it `Enabled`, give it a name such as "Collect in store", and an optional `Fee`). It then appears in the
checkout shipping-method list; selecting it suppresses the shipping address and places the order as a collect
order.

Requires `silvershop/core ^6` and `silvershop/shipping`. Optional integrations (auto-detected):

| Module | What it adds |
|---|---|
| `silvershop-suite/store-profile` | The single implicit pickup location (address + name) shown in the email and admin panel. |
| `silvershop-suite/order-admin` | Surfaces pickup state in the modern order screen (this module ships a standalone Pickup tab otherwise). |
| `silvershop-suite/notification-log` | Logs the ready-for-pickup email from the stamped header. |
| `silvershop-suite/storefront` | Styled checkout delivery-vs-pickup choice and account / confirmation pickup wording. |

## Configuration

```yaml
SilverShop\ClickAndCollect\Extension\OrderPickupExtension:
  # When true, markCollected() also advances the order Status to Complete. Off by default — v1 keeps
  # fulfilment on the datetimes and leaves the payment / status machinery untouched.
  complete_on_collected: false
```

The pickup fee is per-method (the `Fee` field on each `PickupShippingMethod`).

## Roadmap (v2)

Deliberately **not** in v1: multi-location `PickupLocation` model + checkout location picker; time slots;
the per-product pickup-only / not-shippable flag (`silvershop-suite/shippability`) and the mixed-cart
"forces pickup" enforcement; collector-name / notes; hold-period reminders + no-show handling; and the fuller
Fable storefront UX (the Linberg click & collect design is the reference).

## Licence

BSD-3-Clause.

## Credits

Built and maintained by **Remy Vaartjes** (<remy@xd.nl>) — part of the **SilverShop-Suite**.
