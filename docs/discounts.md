# Discounts and coupons

A **discount code** is the string a customer types. A **coupon** is the rule
behind it. They are separate because the same rule usually backs many codes —
one "10% off" coupon, a thousand single-use codes — and because the rule is the
shop's, not the package's.

## The split

`ecommerce_discount_code` stores the code and a pointer to its coupon, as a
class name plus an id:

```
code            varchar    the string the customer types
coupon_class    varchar    the shop's coupon class
coupon_id       int        which one
```

A polymorphic pointer rather than a foreign key, because the package does not
own the coupon table — a shop's coupon can be any model it likes.
`DiscountCode::get_coupon()` resolves it and answers `null` when the class no
longer exists or the row is gone.

## The coupon contract

```php
interface CouponInterface
{
    public function isRedeemable(TotalingInterface $totalingItem): bool;
    public function getReduction(TotalingInterface $totalingItem): int;  // CENTS
    public function redeem(Order $order);
}
```

`TotalingInterface` and not `CartInterface`, because both a cart and an order
are totaling things — so a coupon can be asked what it takes off a cart being
built *and* what it took off an order already placed, with one implementation.

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

## Applying one

```php
$cart->addDiscount($discountCode);
$cart->getDiscount();     // the code in force, or null
$cart->getReduction();    // what it takes off, in cents
```

`addDiscount()` checks `isRedeemable()` before storing, so a code that does not
apply is **silently not applied** — there is no exception and no message. Check
yourself if the customer deserves to be told why:

```php
$coupon = $discountCode->coupon;

if ($coupon === null || !$coupon->isRedeemable($cart)) {
    // your own "this code doesn't apply" message
}
```

## Redeemability is re-checked on every read

A stored code is not trusted. `Cart::getCoupon()` re-runs `isRedeemable()` each
time the cart is asked about its discount, and treats a coupon that has stopped
qualifying as no coupon at all.

That matters because a cart is edited after a code is applied. A code with a
€25 minimum stays applied while the customer adds things and stops applying the
moment they remove enough — without this, the reduction would be frozen in at
the moment the code was typed.

## Redemption happens on payment, not on checkout

The provider registers one listener, on the `Paid` event:

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

The consequence is worth stating plainly: **a shop on `NullPayment` redeems
immediately**, because `NullPayment` dispatches `Paid` from `pay()`.

## How the reduction reaches the lines

`getReduction()` is one figure against the whole cart, but tax is worked out per
line — so the reduction has to be spread across the lines before any of them is
taxed. `Money::apportion()` does that in proportion to line totals, and the
shares add back up to the reduction exactly.

Across **every** line, including untaxed ones. The discount belongs to the cart,
so a share of it belongs to a line that pays no tax; charging the taxable lines
with all of it would tax them on less than the customer actually paid.

See [Tax](tax.md#the-coupon-reduces-what-is-taxed).

## See also

- [Money](money.md) — `percentageOf()`, `apportion()`, and the rounding rule
- [Tax](tax.md) — how a discount changes the taxable base
- [Cart](cart.md) — `addDiscount()`, `getReduction()`
