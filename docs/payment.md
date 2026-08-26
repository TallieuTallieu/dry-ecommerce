# Payment

The smallest interface in the package, and the one most likely to be left at its
default by accident.

```php
interface PaymentInterface
{
    public function pay(OrderInterface $order);
}
```

One method. `Cart::checkout()` calls it once, last, after the order and its
lines are written and the `Created` event has been dispatched — so a gateway
always receives an order that already exists and has a reference.

## Choosing one

```php
// config/ecommerce.php
'payment' => \Tnt\Ecommerce\Payment\NullPayment::class,
```

Resolved through the container, so a gateway can constructor-inject whatever it
needs.

> ### `NullPayment` is the default and it charges nobody
>
> It is the shipped dummy gateway: it behaves exactly like a real synchronous
> gateway whose every payment succeeds, minus the money. It dispatches `Paid`,
> the listener marks the order paid, single-use coupons are redeemed — and no
> money moves, and nothing anywhere warns about it.
>
> It is the right default for a package that cannot know which gateway a shop
> uses, it is what makes a checkout exercisable end to end before payment is
> wired up, and it is a live risk in any project that has not got round to
> payment yet. Set a real gateway (e.g. `dry-mollie`) before launch.
>
> It is also the **reference implementation**: when you write a real gateway,
> copy its shape. Dispatch the event that says what the money did, and let the
> package's listeners write `payment_status` — do not write the column
> yourself.

## What a gateway does

`pay()` is where a gateway redirects, or calls out, or does nothing and waits
for a webhook. The package deliberately says nothing about which — it does not
know whether payment is synchronous, and it does not own the callback route.

What a gateway is expected to do is dispatch the right event when it learns the
outcome:

| Event | Meaning |
| --- | --- |
| `Created` | The order exists. Dispatched by the cart, not by the gateway. |
| `Paid` | The money arrived. |
| `PaymentFailed` | The attempt failed. |
| `PaymentCanceled` | The customer backed out. |
| `PaymentExpired` | The window closed without payment. |
| `PaymentRefunded` | The money went back. |

```php
use Oak\Dispatcher\Facade\Dispatcher;
use Tnt\Ecommerce\Events\Order\Paid;

Dispatcher::dispatch(Paid::class, new Paid($order));
```

Every payment event has behaviour attached: the service provider listens for
each of them and writes the matching status onto the order (see below). `Paid`
additionally redeems the order's coupon. See [Discounts](discounts.md).

## The status lifecycle

`ecommerce_order.payment_status` is written by the package, in two places:

1. **`Cart::checkout()` writes `pending`** before `pay()` runs, so every order
   has a status from birth. An asynchronous gateway leaves it standing until
   its webhook reports; a synchronous one (like `NullPayment`) overwrites it
   before `checkout()` even returns.
2. **The event listeners write the rest.** The service provider registers one
   listener per payment event, each translating the event into a word on the
   column and saving the order:

   | Event | Status written |
   | --- | --- |
   | `Paid` | `paid` |
   | `PaymentFailed` | `failed` |
   | `PaymentCanceled` | `canceled` |
   | `PaymentExpired` | `expired` |
   | `PaymentRefunded` | `refunded` |

So a gateway never touches the column: it dispatches honestly and the column
follows. That is deliberate — a gateway that wrote one word and dispatched
another would leave the database and the listeners disagreeing about the same
payment.

Read it back through the enum:

```php
use Tnt\Ecommerce\Payment\PaymentStatus;

$order->getPaymentStatus();                  // PaymentStatus::Paid
$order->getPaymentStatus() === PaymentStatus::Paid;
```

`getPaymentStatus()` reads anything it cannot parse — including the empty
string on orders from before this lifecycle existed — as
`PaymentStatus::Pending`. Pending is the one status that claims nothing:
nothing ever recorded money arriving for those orders, and inventing any other
answer would assert a report no gateway made.

This is the **payment's** state, not a fulfillment status. "Paid" does not
mean packed, shipped or picked up; a shop that needs a fulfillment life models
it separately.

`payment_id` is the gateway's to fill in — it is where a Mollie or Stripe
transaction id belongs, and it is what a webhook looks an order up by.
`NullPayment` has no transaction to reference and leaves it blank.

## Available gateways

- **Mollie** — `dry-mollie`, https://github.com/reinvanoyen/dry-mollie

Note that `dry-mollie` is pinned to `dry-ecommerce: ^1.2.1` and **cannot be
installed alongside the 4.x line**. Until it is updated, a project on this
version has `NullPayment` and nothing else, and cannot take a payment at all.

## See also

- [Orders](orders.md) — what an order looks like when `pay()` receives it
- [Discounts](discounts.md) — why redemption hangs off `Paid`
