# dry-ecommerce
## E-commerce platform

#### Installation

```ssh
composer require tallieutallieu/dry-ecommerce
```

#### Register the service provider

```php
<?php

$app = new \Oak\Application();

$app->register([
    \Tnt\Ecommerce\EcommerceServiceProvider::class,
]);

$app->bootstrap();
```

##### Config options

Name | Default
---- | -------
payment | \Tnt\Ecommerce\Payment\NullPayment::class

**Careful!** Payment can be set from configuration. the default value of the "payment" config property provides a default NullPayment which basically gives everything away for free. For more info on payments check out the topic payments below.

#### Concepts
* Money
* Buyable
* Cart
* Discount & Coupon
* Fulfillment
* Customer
* Order
* Payment
* Stock
* Tax

### Money

**Every monetary value in this package is an `int` counting cents.** €12.25 is
`1225`. That covers prices, line totals, subtotals, fulfillment costs,
reductions and tax amounts, in the contracts, in the models and in the columns —
`ecommerce_order.{total,subtotal,reduction,fulfillment_cost}` and
`ecommerce_order_item.price` are `bigint`, not `decimal`.

There is no `float` anywhere in that list, because this package adds money up in
loops: `Cart::getSubTotal()` accumulates one line total per item, and float
accumulation drifts. `0.1 + 0.2 + 0.3` is not `0.6`; `10 + 20 + 30` is always
`60`.

`bigint` rather than `int` is deliberate. PHP's `int` is a signed 64-bit
integer, and `bigint` is the only integer column that holds all of it; an
`int(11)` would stop at 2,147,483,647 cents (€21,474,836.47), which is *less*
range than the `decimal(10,2)` it replaces, and MySQL would truncate a large
order rather than refuse it.

#### The rounding rule

Integers do not remove rounding, they concentrate it. Multiplying an amount by a
*rate* — VAT at 6%, 12% or 21%, a percentage discount — produces fractional
cents. The rule, which `Tnt\Ecommerce\Money` implements and which your
`TaxRateInterface` and `CouponInterface` implementations are expected to follow:

> **1. Round half away from zero.** A result of exactly half a cent rounds up in
> magnitude: 21% of `50` is `10.5`, and becomes `11`. Banker's rounding is
> deliberately not used — on a single invoice line it looks arbitrary.
>
> **2. Round once, on the smallest amount the rate genuinely applies to.**
> Per-line VAT is computed and rounded per line; a cart-level percentage
> discount is computed and rounded once, on the subtotal. Totals are then plain
> integer sums of amounts that have already been rounded — never a rate applied
> to a total.

The second half is a real choice, because rounding per line and rounding the
total give different answers. Two lines of `1250` at 21%: per line, `263 + 263 =
526`; on the total, 21% of `2500` = `525`. This package takes `526`. Each line
is a figure that gets printed and can be checked on its own, so the total has to
be the sum of the printed lines — a total that does not add up from the figures
above it is the worse of the two failures on an invoice.

Use `Money::percentageOf()` rather than rolling your own; it is where the rule
lives.

```php
<?php

use Tnt\Ecommerce\Money;

$vat = Money::percentageOf($line->getPrice(), 21); // 21% VAT on one line
$off = Money::percentageOf($cart->getSubTotal(), 10); // 10% off the cart

Money::percentageOf(25, 6); // 1.5 cents -> 2
Money::percentageOf(4999, 10); // 499.9 cents -> 500
```

Rates are honoured to four decimal places of a percent, so `21`, `21.5` and
`0.0625` all land where they should.

### Buyable
Documentation coming soon

### Cart

```php
<?php

$cart = $app->get(CartInterface::class);

$cart->add($buyable, 2);
$cart->remove($buyable);
$cart->clear();
$items = $cart->items();

$cart->setFulfillment($shipping);
$fulfillment = $cart->getFulfillment();
$fulfillmentCost = $cart->getFulfillmentCost();

$cart->addDiscount($discountCode);
$discountCode = $cart->getDiscount();

$subTotal = $cart->getSubTotal();
$total = $cart->getTotal();
$reduction = $cart->getReduction();

$order = $cart->checkout($customer);
```

### Discount & Coupon
Documentation coming soon

### Fulfillment
Documentation coming soon

### Customer
Documentation coming soon

### Order
Documentation coming soon

### Payment
Documentation coming soon.

Available payment packages:
* Mollie: https://github.com/reinvanoyen/dry-mollie

### Stock
Documentation coming soon

### Tax
Documentation coming soon
