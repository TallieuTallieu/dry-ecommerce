# Buyable

A buyable is anything your shop sells. `BuyableInterface` asks for five things,
all of which any model that is for sale can answer:

```php
<?php

use Tnt\Ecommerce\Contracts\BuyableInterface;

class Product extends \dry\orm\Model implements BuyableInterface
{
    public function getId(): string { return (string) $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): string { return $this->description; }
    public function getPrice(): int { return $this->price; } // cents
    public function getThumbnailSource(): string { return $this->image->src; }
}
```

That is a complete buyable. It has no stock and no tax, and it does not need
any: both are **capabilities you opt into**, one interface each.

| Implement | To get |
|---|---|
| `HasStockInterface` | `getStockWorker()`. `Cart::canAdd()` reports whether the stock covers a quantity. |
| `TaxableInterface` | `getTaxRate()`. The buyable's lines count towards `Cart::getTax()`. |

Neither, either or both. The cart checks with `instanceof` and asks a buyable
nothing it has not offered to answer.

> **Upgrading.** `getStockWorker()` and `getTaxRate()` used to be mandatory on
> every buyable, so anything with no stock concept and no VAT rate had to invent
> answers — and the package shipped the inventions, `NullStockWorker` and
> `NullTaxRate`, so that it could. Both are gone. Move the two methods onto the
> matching capability interface, or delete them: a buyable that returned a null
> implementation was saying it had neither, and now says so by implementing
> neither.

## About the id

`getId()` returns a `string`, but the value has to be an integer written as one.
Cart lines and order lines reference their buyable by class name plus `item_id`,
and both `item_id` columns are `int(11)`; a buyable answering `'sku-a'` would be
stored, and read back, as item 0. A model returns `(string) $this->id`.

