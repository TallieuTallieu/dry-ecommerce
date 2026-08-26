# Stock

Optional. A buyable that is counted implements `HasStockInterface` and names the
stock it is counted in:

```php
<?php

use Tnt\Ecommerce\Contracts\HasStockInterface;
use Tnt\Ecommerce\Contracts\StockWorkerInterface;
use Tnt\Ecommerce\Stock\StockWorker;

class Product extends \dry\orm\Model implements HasStockInterface
{
    // ... the five BuyableInterface methods ...

    private ?StockWorkerInterface $stockWorker = null;

    public function getStockWorker(): StockWorkerInterface
    {
        // Held rather than rebuilt: a worker looks its stock row up on first
        // use and then remembers it, and `Cart::canAdd()` asks for one on
        // every call. Returning a new worker each time throws that away.
        return $this->stockWorker ??= new StockWorker('warehouse');
    }
}
```

A shop can keep several stocks — a warehouse, a shop floor — as rows in
`ecommerce_stock`, addressed by `hid`. `StockWorker` counts one of them, looking
the row up on first use, so building one costs nothing.

```php
$worker->getQuantity($buyable);              // how many there are
$worker->isAvailable($buyable, 3);           // are there three?
$worker->increment($buyable, 10);            // stock arrived
$worker->decrement($buyable, 1);             // one went out
```

Quantities are whole. They were `float` in these signatures and `int` in the
column, which meant half a thing was expressible and not storable; a shop that
sells by weight counts in grams, exactly as its money is in cents. `increment()`
and `decrement()` dispatch `Events\Stock\Incremented` and `Decremented` with the
amount that moved. A stock with no line for a buyable reports 0 and refuses it —
never stocked is not the same as unlimited.

## Taking out more than there is

Your call, made once when you build the worker:

```php
new StockWorker('warehouse');                       // refuses
new StockWorker('warehouse', allowNegative: true);  // backorders
```

By default `decrement()` refuses to take a stock below zero and throws
`Stock\StockWouldGoNegative`, which carries the buyable, what the stock held,
what was asked for and the shortfall. Nothing is written when it refuses — the
count is left exactly as it was, not partly taken out.

With `allowNegative: true` the count goes under instead, and the negative is how
many you owe; `increment()` counts it back up again as deliveries arrive.
`isAvailable()` is unaffected either way — a stock at `-2` has nothing available.

The count is never clamped to zero, under either policy. A stock that quietly
disagrees with what was taken out of it is the one outcome that helps nobody, so
the choice is between hearing about the oversell and recording it.

> Nothing in this package calls `decrement()`, so this fires wherever you do —
> usually a listener on `Events\Order\Paid`, which is *after* the money is taken.
> `Cart::canAdd()` is the earlier and cheaper place to find out.

The cart is the only part of the package that consults stock on its own, in
`canAdd()`, and it only reports what it finds. Nothing decrements automatically
at checkout either; when to take stock out — on order, on payment, on dispatch —
is the shop's call, and `Events\Order\Paid` is the usual place to make it.

`StockWorkerInterface` is deliberately not bound in the container: a worker
cannot be built without being told which stock it counts, so there is nothing
sensible to resolve. Buyables hand one over themselves.

