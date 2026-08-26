# dry-ecommerce

An e-commerce package for dry3: a cart, a checkout, orders, stock, tax and an
address book, with the parts a shop has to decide for itself left as interfaces
rather than guessed at.

**Full documentation is in [`docs/`](docs/index.md).**

## Requirements

PHP `>= 8.4`, dry `^4.0`, oak `^3.0`, dry-dbi `^3.0`. dry-accounts `^3` is a
supported pairing, not a dependency.

## Install

```sh
composer require tallieutallieu/dry-ecommerce
```

Register the service provider — after dry-accounts', if the shop uses it:

```php
$app->register([
    \Tnt\Ecommerce\EcommerceServiceProvider::class,
]);

$app->bootstrap();
```

Run the migrations:

```sh
php oak migration migrate
```

Configure it in `config/ecommerce.php`:

```php
<?php

return [
    'payment' => \Tnt\Ecommerce\Payment\NullPayment::class,
    'user_resolver' => \Tnt\Ecommerce\Account\GuestUserResolver::class,
    'prices' => 'inclusive',
    'delivery_tax_rate' => 0,
];
```

| Key | Default | Decides |
| --- | --- | --- |
| `payment` | `NullPayment::class` | The gateway. |
| `user_resolver` | `GuestUserResolver::class` | Whether a checkout can link to an account. |
| `prices` | `inclusive` | Whether quoted prices already contain their tax. |
| `delivery_tax_rate` | `0` | The rate charged on fulfillment cost. |

> **`NullPayment` gives everything away for free.** It marks an order paid
> without taking any money, and it is the default. Replace it before going live.

See [Installation](docs/installation.md) for the whole of it, including the
path-repository arrangement needed while 4.x is unreleased.

## Quickstart

### Make something sellable

```php
use Tnt\Ecommerce\Contracts\BuyableInterface;

class Product extends Model implements BuyableInterface
{
    public function getId(): string { return (string) $this->id; }
    public function getTitle(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getThumbnailSource(): string { return $this->photo_url; }

    /** In CENTS. €12.50 is 1250. */
    public function getPrice(): int { return $this->price_in_cents; }
}
```

Stock and tax are opt-in capabilities — implement `HasStockInterface`,
`TaxableInterface`, both, or neither. A buyable that implements neither is a
complete buyable: always addable, and it contributes no tax.

### Fill a cart

```php
$cart = $app->get(\Tnt\Ecommerce\Contracts\CartInterface::class);

$cart->canAdd($product, 3);   // does the stock cover it? reported, not enforced
$cart->add($product, 3);
$cart->add($product, 1, ['cheese' => 'no goat']);   // per-line options: its own line

$cart->getSubTotal();         // cents
$cart->getTotal();            // cents
$cart->getTax();              // cents
```

### Check out

```php
$customer = new \Tnt\Ecommerce\Model\Customer();
// ... name, email, and at least one address in the book ...
$customer->save();

$customer->useAddress($billingAddress);
$customer->useAddress($shippingAddress);

$order = $cart->checkout($customer);

$order->order_id;              // '12-K4M7QX9RTB'
$order->getBillingAddress();   // a frozen copy, not the live row
```

Guest and account checkout are the same call. See
[Customer](docs/customer.md#a-worked-checkout-end-to-end) for the full worked
example.

## The three things that surprise people

**1. Money is cents, everywhere.** `getPrice()` returns `1250` for €12.50. A
model whose column holds euros converts in `getPrice()` and nowhere else.
[Money](docs/money.md).

**2. Stock and tax are optional.** `BuyableInterface` asks for five things and
neither a stock worker nor a tax rate is among them. [Buyable](docs/buyable.md).

**3. The package reports; the shop decides.** `canAdd()` says whether the stock
covers a quantity and `add()` adds regardless — refusing the sale, backordering
and overselling on purpose are all real ways to run a shop.
[Cart](docs/cart.md).

## Known gaps

- **One tax rate per line** — by design. A line mixing rates is the shop's job
  to split into one line per rate. [Options](docs/options.md).
- **Checkout needs a web request** when accounts are configured.
  [Installation](docs/installation.md#checking-it-works).

The full, honest list lives in [docs/index.md](docs/index.md#known-gaps).

## Development

```sh
make test            # Pest
make phpstan         # level 9
make sync-docs       # rsync docs/ to OBSIDIAN_DOCS_PATH
```

## Documentation

| | |
| --- | --- |
| [Installation](docs/installation.md) | Setup, start to finish |
| [Money](docs/money.md) | Cents, and the rounding rule |
| [Buyable](docs/buyable.md) | The contract and its two capabilities |
| [Cart](docs/cart.md) | Lines, totals, checkout |
| [Options and variants](docs/options.md) | Per-line choices, in the merge key and frozen onto orders |
| [Customer](docs/customer.md) | Guests, accounts, dry-accounts |
| [Addresses](docs/addresses.md) | The book, and what an order freezes |
| [Orders](docs/orders.md) | What an order records |
| [Fulfillment](docs/fulfillment.md) | Delivery methods and their attributes |
| [Discounts and coupons](docs/discounts.md) | Codes and the rules behind them |
| [Stock](docs/stock.md) | Counting, and running out |
| [Tax](docs/tax.md) | Rates and price conventions |
| [Payment](docs/payment.md) | The gateway interface |
| [What changed from 1.x](docs/from-1x.md) | For reading older projects |
