# Discounts and coupons

A **discount code** is the string a customer types. A **coupon** is the rule
behind it. They are separate because the rule decides what comes off and the
code only names it — and because a shop may bring its own rule.

The package ships one rule of its own, `Tnt\Ecommerce\Model\Coupon`: a
percentage off, or a fixed amount off until its balance is spent. A shop that
needs something else implements `CouponInterface` on a model of its own; the
cart treats both the same.

## The split

`ecommerce_discount_code` stores the code and a pointer to its coupon, as a
class name plus an id:

```
code            varchar    the string the customer types — uppercase, unique
coupon_class    varchar    the coupon's class
coupon_id       int        which one
```

A polymorphic pointer rather than a foreign key, because the package does not
own every coupon table — a shop's coupon can be any model it likes.
`DiscountCode::get_coupon()` resolves it and answers `null` when the class no
longer exists or the row is gone.

## Codes

Codes are stored **trimmed and uppercase**, and `ecommerce_discount_code.code`
is unique. `DiscountCode::save()` normalises what it writes and
`DiscountCodeRepository::byCode()` normalises what it looks up, so a customer
typing `summer10` finds `SUMMER10`:

```php
$code = DiscountCodeRepository::create()->byCode($typed)->firstOrNull();
```

`DiscountCode::normalise()` is the one place the rule lives.

> [!warning] Behaviour change
> Before this release `byCode()` matched the code as typed, trimmed only. A
> shop that relied on two codes differing in case gets one of them refused
> by the `MakeDiscountCodeUnique` revision — see
> [Installation](installation.md#migrations).

## The shipped coupon

`ecommerce_coupon`, one row per coupon. The code is **not** on it: it lives in
`ecommerce_discount_code` like every coupon's, behind the virtual `code`.

A code row always points at `Tnt\Ecommerce\Model\Coupon`, whichever class
saved it, so the package's admin and a project read the same row as the same
coupon. `Coupon` is therefore not for subclassing: a shop that needs other
rules implements `CouponInterface` on its own model (see
[The coupon contract](#the-coupon-contract)).

| Column     | Holds                                                                                       |
| ---------- | ------------------------------------------------------------------------------------------- |
| `type`     | `percentage` or `fixed` — `Tnt\Ecommerce\Discount\CouponType`.                              |
| `value`    | Cents for a fixed coupon; **hundredths of a percent** for a percentage one (`750` is 7.5%). |
| `balance`  | Cents left on a fixed coupon. Starts at `value`; only redemption writes it.                 |
| `starts`   | Unix time it starts applying, or `NULL`.                                                    |
| `ends`     | Unix time it stops — 23:59:59 of the chosen day — or `NULL`: it never expires.              |
| `minimum`  | Cents the lines that take a discount must come to, or `NULL`.                               |
| `max_uses` | How often it may be used, or `NULL` (also `0`): unlimited.                                  |
| `uses`     | How often it has been. Only redemption writes it.                                           |
| `note`     | Free text for the shop.                                                                     |

```php
use Tnt\Ecommerce\Model\Coupon;

$coupon = Coupon::percentage('7,5'); // 7.5%, max_uses 1
$coupon->code = 'summer10'; // stored as SUMMER10; empty = generated
$coupon->save();

$gift = Coupon::fixed(5000); // €50 balance, unlimited uses
$gift->save(); // gets an eight-character code
echo $gift->code; // e.g. 7KQ2M9XA
```

A **percentage** coupon defaults to one use: the common case is a code handed
to one customer. A **fixed** coupon has no limit on uses — it is spent when its
balance is, across as many orders as that takes.

A percentage is entered as `7,5` or `7.5`, at most two decimals, between 0.01
and 100; anything else raises `Tnt\Ecommerce\NotAPercentage`. It is stored as
an integer so `7.5` never becomes `7.4999…`.

`Coupon::save()`:

- **generates** a code when none is given — eight characters of Crockford's
  base 32 (`Tnt\Ecommerce\Discount\CodeGenerator`), retried on a collision;
- refuses a typed code another coupon holds, in any case, with
  `Tnt\Ecommerce\CouponCodeRefused`; the unique index is the backstop;
- refuses to **change the code once the coupon has been used** — a customer
  may still hold the old one;
- never writes `balance` or `uses` on an existing row. dry's `Model::save()`
  rewrites every loaded column, so an admin saving a note would otherwise put
  back a balance a payment had just taken from.

A coupon that takes off nothing — a fixed amount of 0, a percentage left
empty — raises `Tnt\Ecommerce\CouponTakesNothing`; a euro amount it cannot
read (`€12`, `1.250,00`) raises `Tnt\Ecommerce\NotACouponAmount`. Each refusal is an
`InvalidArgumentException` that implements dry's `ConvertableToApiException`,
so the admin's save answers with a code and the field it concerns rather than
a bare `php.exception`:

| Exception                     | `error_code`                          | `field`                      |
| ----------------------------- | ------------------------------------- | ---------------------------- |
| `CouponCodeRefused::taken()`  | `ecommerce.coupon_code_taken`         | `code`                       |
| `CouponCodeRefused::locked()` | `ecommerce.coupon_code_locked`        | `code`                       |
| `NotAPercentage`              | `ecommerce.coupon_percentage_invalid` | `percent`                    |
| `CouponTakesNothing`          | `ecommerce.coupon_value_invalid`      | `amount` or `percent`        |
| `NotACouponAmount`            | `ecommerce.coupon_amount_invalid`     | `amount` or `minimum_amount` |

## The coupon contract

```php
interface CouponInterface
{
    public function isRedeemable(TotalingInterface $totalingItem): bool;
    public function getReduction(TotalingInterface $totalingItem): int; // CENTS
    public function redeem(Order $order);
}
```

`TotalingInterface` and not `CartInterface`, because both a cart and an order
are totaling things — so a coupon can be asked what it takes off a cart being
built _and_ what it took off an order already placed, with one implementation.

Two optional interfaces sit beside it; `CouponInterface` itself is unchanged.

| Interface                  | Says                                                                     |
| -------------------------- | ------------------------------------------------------------------------ |
| `RefusesCouponInterface`   | `refusal($totaling): ?CouponRefusal` — why it does not apply.            |
| `ReachesDeliveryInterface` | `reachesDelivery(): bool` — whether its reduction may come off delivery. |

The shipped coupon implements both. A shop's own coupon may implement either,
both or neither.

## What a coupon reaches

A reduction comes off the **lines that take a discount**, and — for a coupon
that reaches it — **delivery**:

| Coupon                                           | Reaches                                         |
| ------------------------------------------------ | ----------------------------------------------- |
| Percentage                                       | The lines that take a discount. Never delivery. |
| Fixed                                            | Those lines **and** delivery.                   |
| A shop's own, answering `reachesDelivery()` true | Those lines and delivery.                       |
| Any other                                        | The lines that take a discount.                 |

A line takes a discount unless its buyable implements
`DiscountableInterface` and answers `takesDiscount()` false — a deposit, a gift
card:

```php
class Deposit extends Model implements DiscountableInterface
{
    // ... the five BuyableInterface methods ...

    public function takesDiscount(): bool
    {
        return false;
    }
}
```

**The cart clamps every coupon to what it may reach.** `Cart::getReduction()`
is `min(coupon->getReduction($cart), what it may reach)`, whichever coupon is
in force. A percentage is worked out on the lines that take a discount and
rounded once; a fixed coupon takes `min(balance, those lines + delivery)`.

> [!warning] Behaviour change
> Before this release the cart took whatever a coupon said, even past the
> subtotal. A shop's own coupon is now held to the lines that take a discount —
> and to no delivery unless it implements `ReachesDeliveryInterface`. A coupon
> that already stayed within the subtotal, in a cart with no opted-out lines,
> takes off exactly what it did.

`Tnt\Ecommerce\Discount\Reach` holds the arithmetic: the reach, the clamp and
the split. `Reach::of($cart)` reads a cart; `Reach::fromAmounts()` runs the
same arithmetic over amounts a project holds itself — a frozen order's lines,
for a VAT breakdown of its own. The cart calls the same three methods, so a
project calls them rather than copying the rule:

| Method                                   | Does                                                                        |
| ---------------------------------------- | --------------------------------------------------------------------------- |
| `Reach::reachesDelivery(?$coupon)`       | Whether the coupon reaches delivery: `ReachesDeliveryInterface`, true.      |
| `$reach->clamp($coupon, $totaling)`      | The coupon's reduction, held between 0 and what it may reach.               |
| `$reach->splitFor($reduction, ?$coupon)` | The reduction spread over the lines, and delivery if the coupon reaches it. |

```php
use Tnt\Ecommerce\Discount\Reach;

$split = Reach::fromAmounts(
    [
        ['price' => 3000, 'takesDiscount' => true],
        ['price' => 500, 'takesDiscount' => false], // the deposit
    ],
    (int) $order->fulfillment_cost
)->split($order->getReduction(), $order->discountReachedDelivery());

$split['lines']; // [0 => share, 1 => 0], in the order the cart had them
$split['delivery']; // delivery's share — what the order froze as
// fulfillment_reduction
```

`split($reduction, $withDelivery)` is `splitFor()` for a caller that already
knows whether delivery is reached. `Order::discountReachedDelivery()` knows it
for an order: what the coupon says while its code and the coupon still exist,
else whether the order froze anything off delivery — see
[Orders](orders.md#the-discount-it-was-placed-with).

> [!warning] A fulfillment cost may read only the subtotal
> A fixed coupon reaches delivery, so working out its reduction asks
> `FulfillmentInterface::getCost($cart)`. A `getCost()` that reads
> `getTotal()` or `getReduction()` loops back into the coupon. Read
> `getSubTotal()` or the lines — see [Fulfillment](fulfillment.md).

## How the reduction reaches the lines

`getReduction()` is one figure against the whole cart, but tax is worked out per
line — so the reduction is spread before any line is taxed, in **one**
`Money::apportion()` over what it reaches: each line that takes a discount
weighted by its total, an opted-out line weighing 0, and delivery last when it
is reached. The shares add back up to the reduction exactly, and none exceeds
what it comes off.

Untaxed lines take their share too. The discount belongs to the cart, so
charging the taxable lines with all of it would tax them on less than the
customer actually paid. Delivery's share comes off before
`taxOnDelivery()`. See [Tax](tax.md#the-coupon-reduces-what-is-taxed).

With nothing opted out and delivery out of reach, this is exactly the split the
cart always made.

## Applying one

```php
$refused = $cart->applyCode($typed); // null: it is on the cart
$cart->getDiscount(); // the code in force, or null
$cart->getReduction(); // what it takes off, in cents
$cart->removeDiscount(); // take it off again
```

`applyCode()` takes the string as posted: it looks the code up in any case,
spaces around it trimmed (`DiscountCodeRepository::byCode()`), and puts it on
the cart — or answers why not and leaves the cart as it was. One code per
cart:

- a code that applies **replaces** the one in force;
- a refused code **leaves** the one in force standing;
- the code in force typed again changes nothing and answers null.

> [!warning] Interface change
> `CartInterface` gained `applyCode()` and `removeDiscount()`. A shop with
> its own implementation of `CartInterface` — not a subclass of `Cart` — no
> longer satisfies it until it adds both. `RefusedCode::of()` does the
> refusing for it; see below.

`addDiscount($code)` is still there for a shop that holds a `DiscountCode`
row already. It checks `isRedeemable()` before storing, so a code that does
not apply is **silently not applied** — no exception and no message.

### Telling the customer why

`applyCode()` answers a `Tnt\Ecommerce\Discount\RefusedCode`, or null when the
code is on the cart. It carries the reason and what a message for it needs:

| Property  | Holds                                                                                        |
| --------- | -------------------------------------------------------------------------------------------- |
| `reason`  | A `Tnt\Ecommerce\Discount\CouponRefusal` case — see the table below.                         |
| `starts`  | For `NotStarted`: the Unix time the coupon starts applying. Otherwise `null`.                |
| `minimum` | For `BelowMinimum`: the cents the lines that take a discount must come to. Otherwise `null`. |

```php
use Tnt\Ecommerce\Discount\CouponRefusal;

$refused = $cart->applyCode($typed);

if ($refused === null) {
    // applied
} elseif ($refused->reason === CouponRefusal::NotStarted) {
    $from = $refused->starts; // Unix time; null for a shop's own coupon
} elseif ($refused->reason === CouponRefusal::BelowMinimum) {
    $atLeast = $refused->minimum; // cents; null for a shop's own coupon
} else {
    // your own message per case
}
```

| Case           | When                                                                                |
| -------------- | ----------------------------------------------------------------------------------- |
| `Missing`      | Nothing typed, or only spaces. Only `applyCode()` answers it.                       |
| `Unknown`      | No such code, no coupon behind it, or a coupon that does not say why and says no.   |
| `NotStarted`   | Before `starts`.                                                                    |
| `Expired`      | After `ends`.                                                                       |
| `UsedUp`       | `uses` has reached `max_uses`.                                                      |
| `Empty`        | A fixed coupon with no balance left.                                                |
| `BelowMinimum` | The lines that take a discount come to less than `minimum` — delivery never counts. |

The shipped coupon checks them in that order. A cart with nothing a coupon can
reach is not refused: the coupon applies and takes off 0.

Only the shipped coupon has a start and a minimum to give. A shop's own
coupon's refusal — through `RefusesCouponInterface`, or `Unknown` for one that
only says no — carries `null` for both.

A shop with its own cart, or a code row in hand, asks the same question
without applying anything:

```php
use Tnt\Ecommerce\Discount\CouponRefusal;
use Tnt\Ecommerce\Discount\RefusedCode;

$code = DiscountCodeRepository::create()->byCode($typed)->firstOrNull();

RefusedCode::of($code, $cart); // with starts and minimum, or null
CouponRefusal::of($code, $cart); // the reason alone, or null
```

## Redeemability is re-checked on every read

A stored code is not trusted. `Cart::getCoupon()` re-runs `isRedeemable()` each
time the cart is asked about its discount, and treats a coupon that has stopped
qualifying as no coupon at all.

That matters because a cart is edited after a code is applied. A code with a
€25 minimum stays applied while the customer adds things and stops applying the
moment they remove enough — and a coupon another order spent while this cart was
open is not placed with this one.

## Redemption

Redemption happens on payment, not on checkout. The provider registers one
listener, on the `Paid` event:

```php
$dispatcher->addListener(Paid::class, function (Paid $event) {
    $coupon = $event->getOrder()->discount?->coupon;

    if ($coupon !== null && $coupon->isRedeemable($order)) {
        $coupon->redeem($order);
    }
});
```

So a single-use code is spent when the money arrives, not when the order is
written — an abandoned or failed payment leaves the code usable, which is what
a customer retrying a payment needs.

**A shop on `NullPayment` redeems immediately**, because `NullPayment` settles
with a capture on the spot and the ledger dispatches `Paid` before `place()`
returns.

### The quote is honoured

Between placing and paying, the coupon may change: another order spends its
balance, it reaches its last use, it expires. The customer was quoted a
reduction, and the order froze it — so for an **order**, the shipped coupon
answers what the order took off: `getReduction($order)` is
`$order->getReduction()` when the order was placed with this coupon's code, and
`isRedeemable($order)` is true when that holds and the reduction is above 0.
The listener above reaches `redeem()` whatever happened since placement.

`Coupon::redeem()` then, without ever loading and re-saving the coupon:

1. **claims**: inserts an `ecommerce_coupon_redemption` row — `amount` the
   order's reduction, `shortfall` `NULL`. `UNIQUE(order)` makes a second claim
   for the same order fail, so a replayed `Paid` redeems nothing.
2. **takes**, in one statement. Fixed:
   `balance = balance - LAST_INSERT_ID(IF(max_uses IS NULL OR max_uses = 0 OR uses < max_uses, LEAST(balance, ?), 0)), uses = uses + 1`
   — the balance never goes below 0, a use past `max_uses` takes nothing, and
   `LAST_INSERT_ID(expr)` hands back what was taken to this connection alone.
   Percentage: `uses = LAST_INSERT_ID(uses + 1)`.
3. **settles**: writes the shortfall — for a fixed coupon what the balance
   and the use limit could not cover; for a percentage coupon the whole amount
   when the use went past `max_uses`, else 0.

Two orders quoted €30 each against a €50 balance both pay: the balance ends at
0, the second redemption records a shortfall of €10, `uses` is 2, and both
orders keep their €30 off. A shortfall is the shop's to absorb or chase; the
package records it and moves nothing.

`shortfall` `NULL` means claimed but not settled — a redemption interrupted
between steps 1 and 3.

**The package never starts a transaction.** A project runs `place()` and a €0
payment inside its own, and dry's `Connection::start_transaction()` is a bare
`START TRANSACTION`, which would commit the project's. Each step is one
statement that is safe on its own.

## Writing your own coupon

```php
class PercentageOff implements CouponInterface
{
    public function isRedeemable(TotalingInterface $item): bool
    {
        return $item->getSubTotal() >= 2500 && $this->uses_left > 0;
    }

    public function getReduction(TotalingInterface $item): int
    {
        // Once, on the subtotal — see the rounding rule.
        return Money::percentageOf($item->getSubTotal(), $this->percentage);
    }

    public function redeem(Order $order)
    {
        $this->uses_left--;
        $this->save();
    }
}
```

The cart clamps it like any coupon. To read the lines that take a discount
rather than the subtotal, use `Reach::of($item)->lines()`. Mind the window
between placing and paying: `isRedeemable($order)` is asked again on `Paid`,
and a coupon that answers false then is not redeemed although the order took
the reduction.

## See also

- [Money](money.md) — `percentageOf()`, `apportion()`, and the rounding rule
- [Tax](tax.md) — how a discount changes the taxable base
- [Cart](cart.md) — `applyCode()`, `getReduction()`
- [Admin](admin.md#coupons) — the coupon screen
