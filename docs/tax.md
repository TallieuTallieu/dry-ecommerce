# Tax

Optional. A buyable that carries tax implements `TaxableInterface` and returns a
rate; one that does not contributes no tax and is asked nothing.

## A rate states the rate

The package ships no rate of its own. Yours states a percentage and stops —
working out what that comes to belongs to `Money`, and whether the amount
already contains it belongs to the shop:

```php
<?php

use Tnt\Ecommerce\Contracts\TaxableInterface;
use Tnt\Ecommerce\Contracts\TaxRateInterface;

final class Vat implements TaxRateInterface
{
    public function __construct(private readonly float $percentage) {}

    public function getPercentage(): int|float
    {
        return $this->percentage;
    }
}

class Product extends \dry\orm\Model implements TaxableInterface
{
    // ... the five BuyableInterface methods ...

    public function getTaxRate(): TaxRateInterface
    {
        return new Vat($this->vat_percentage);
    }
}
```

## Do your prices include tax?

**This is the one thing you have to tell the package**, because it cannot be
inferred and everything else follows from it. Set `ecommerce.prices` to
`inclusive` or `exclusive`.

A price of `1250` at 21% is either €12.50 *of which* €2.17 is VAT, or €12.50
*plus* €2.63 of VAT. The two differ by the whole tax amount — here for a cart of
**two lines of `1250`**, delivered for `475`:

| | `inclusive` | `exclusive` |
|---|---|---|
| subtotal | 2500 | 2500 |
| VAT | *434, contained* | **526, added** |
| delivery | 475 | 475 |
| **total** | **2975** | **3501** |

Two lines rather than one because the VAT differs: 21% of `2500` in a single sum
is `525`, but each line rounds on its own, and `263` twice is `526`. That is [the
rounding rule](money.md#the-rounding-rule), not a slip.

Under `inclusive` — the Belgian consumer norm, and the default — the tax is a
figure to **report**: `getTotal()` is exactly what it always was, and
`getTax()` tells you how much of it was VAT. Under `exclusive` it is an amount
to **charge**, and it lands in the total.

The default is `inclusive` precisely because it leaves an upgrading shop's
totals untouched. Anything unrecognised reads as `inclusive` too, so a typo
costs you a wrong tax figure rather than 21% on every total in the shop.

Each order **records the convention it was placed under**, in
`ecommerce_order.prices`. That is not bookkeeping: without it, a shop that
switches convention would reprint every old invoice with a total it never
charged.

## The coupon reduces what is taxed

A coupon comes off the cart, and tax is worked out per line, so the reduction is
spread across the lines in proportion to their totals — largest remainder, so
the parts sum to the reduction exactly — and each line is taxed on what is left
of it.

```
2000 at 21%, 500 at 6%, coupon 250 off

  line A: 250 x 2000/2500 = 200  ->  (2000-200) at 21% = 378
  line B: 250 x  500/2500 =  50  ->  ( 500- 50) at  6% =  27
```

It is spread over *every* line, including untaxed ones. A discount applies to
the whole cart, so charging the taxable lines with all of it would tax them on
less than the customer paid.

Per-line rounding still holds: a line is the figure that gets printed, so the
total is the sum of the printed lines. See [the rounding
rule](money.md#the-rounding-rule).

## Delivery

Set `ecommerce.delivery_tax_rate` to a percentage and the fulfillment cost is
taxed at it. Leave it unset and it is `0`, so delivery costs no tax. Anything
that is not a number — a string, a stray `true` — reads as `0` too, rather than
being coerced into a rate nobody chose.

> [!warning] One rate for the whole shop
> Belgian VAT treats delivery as ancillary to what is being delivered, so a
> cart of 6% goods should carry 6% on its delivery and a mixed cart should
> apportion it across the rates in it. A single shop-wide rate reports the same
> figure whatever is in the cart: exact for a shop selling at one rate,
> approximate for a shop selling at several. Delivery is small beside the
> goods, so the error is small — but if you cannot accept it, this package does
> not apportion delivery and you will need to.
