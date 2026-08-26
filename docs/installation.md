# Installation

Putting the package into a dry3 project, from `composer require` to a cart that
adds up. Written against a real integration (Delhaize Nederename, sc-11225), so
the order below is the order things actually have to happen in.

## Requirements

| | |
| --- | --- |
| PHP | `>= 8.4` |
| dry | `^4.0` |
| oak | `^3.0` |
| dry-dbi | `^3.0` |
| dry-accounts | `^3` — **optional**, and in `require-dev` here |

dry-accounts is a supported pairing, not a dependency. Exactly one class in
`src/` names it (`AccountsUserResolver`), only in a constructor type hint, which
PHP resolves lazily — so a shop with no accounts never loads that file and the
package installs and runs without it. See [Customer](customer.md).

## Getting the package

```sh
composer require tallieutallieu/dry-ecommerce
```

The repository is not on Packagist, so the project needs the VCS repository as
well:

```json
"repositories": [
  { "type": "vcs", "url": "git@github.com:TallieuTallieu/dry-ecommerce.git" }
]
```

### While 4.x is unreleased

The newest tag is `3.5.0`; the 4.x line lives on master and requires `dry ^4.0`.
Until it is tagged, a project resolves it from a sibling checkout instead:

```json
"repositories": [
  { "type": "path", "url": "../dry-ecommerce", "options": { "symlink": true } }
],
"require": { "tallieutallieu/dry-ecommerce": "@dev" }
```

Two things this breaks that are easy to miss:

- **Docker.** `../dry-ecommerce` is outside the project mount, so the container
  needs it mounted or the symlink in `vendor/` dangles:
  ```yaml
  volumes:
    - .:/var/www/html
    - ../dry-ecommerce:/var/www/dry-ecommerce
  ```
- **Deployment.** A path repository is a development arrangement. Nothing built
  this way is deployable — switch to the tagged release first.

## Registering the service provider

```php
$app->register([
    // ...
    \Tnt\Dbi\RepositoryProvider::class,
    \Tnt\Account\AccountServiceProvider::class,

    \Tnt\Ecommerce\EcommerceServiceProvider::class,
]);
```

**After** dry-accounts, if the shop uses it: `AccountsUserResolver` is
constructed with that package's `AuthenticationInterface`.

The provider binds the cart, the shop, the attribute and cart storages, the
payment gateway, the user resolver and the tax policy, and — in console context
only — registers its migrator.

## Configuration

`config/ecommerce.php`. Every key has a default, and every default is the
reading that leaves a shop's totals where they are.

| Key | Default | What it decides |
| --- | --- | --- |
| `payment` | `NullPayment::class` | The gateway. See the warning below. |
| `user_resolver` | `GuestUserResolver::class` | Whether a checkout can link to an account. |
| `prices` | `inclusive` | Whether quoted prices already contain their tax. |
| `delivery_tax_rate` | `0` | The rate charged on fulfillment cost. |

```php
<?php

return [
    'payment' => \Tnt\Ecommerce\Payment\NullPayment::class,
    'user_resolver' => \Tnt\Ecommerce\Account\AccountsUserResolver::class,
    'prices' => 'inclusive',
    'delivery_tax_rate' => 0,
];
```

> **`NullPayment` gives everything away for free.** It marks an order paid
> without taking any money and it is the default, so a shop that never sets
> `payment` has a working checkout that charges nobody. Replace it before going
> live.

Anything unset, or set to a value the package does not recognise, falls back to
the default rather than erroring — a typo in a config file costs a wrong tax
figure, not a shop that will not boot.

## Migrations

The provider registers a migrator named `ecommerce` with thirteen revisions:
ten create the tables below, and the ones after them alter existing tables
(the frozen `fulfillment_attributes` column on `ecommerce_order`, the
per-line `options` columns on both line tables, and the drop of the address
name columns):

```
ecommerce_customer          ecommerce_cart
ecommerce_discount_code     ecommerce_cart_item
ecommerce_fulfillment_method ecommerce_stock
ecommerce_order             ecommerce_stock_item
ecommerce_order_item        ecommerce_address
```

```sh
php oak migration migrate
php oak migration list        # ecommerce (13/13)
```

Revisions are **appended to the list, never inserted into it.** Oak's migrator
records how many revisions a shop has run, not which — so adding a new revision
next to the table it relates to would renumber everything after it and make an
existing shop run the wrong statement. `CreateAddressTable` and the revisions
after it sit at the end for exactly this reason, not because they came last
conceptually.

### Upgrading a shop that predates the address book

A shop from before `ecommerce_address` carried its addresses as twelve inline
columns on `ecommerce_customer` (`address_*` and `shipping_*`). The migrator
only creates the new table; moving the data over is a by-hand step, one
`INSERT … SELECT` per kind:

```sql
INSERT INTO ecommerce_address
  (created, updated, customer, type, is_default,
   street, number, postal_code, city, country)
SELECT UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), id, 'billing', 1,
       address_street, address_number,
       address_postal_code, address_city, address_country
FROM ecommerce_customer
WHERE address_street <> '';
-- and the same again with type 'shipping' from the shipping_* columns
```

The old inline columns carried recipient names; the book does not — an address
is purely a *where*, and the identity an order is placed under is frozen on the
order itself (see [addresses](addresses.md)). The old name columns are simply
not carried over.

Adapt the column list to what the old schema actually held, and leave the old
columns in place until the shop's own code stops reading them. Orders are
unaffected either way — they froze their copies at checkout.

## Making something sellable

The one thing the package cannot provide. A model becomes sellable by
implementing [`BuyableInterface`](buyable.md) — five methods, all of which any
model that is for sale can already answer:

```php
use Tnt\Ecommerce\Contracts\BuyableInterface;

class Product extends Model implements BuyableInterface
{
    public function getId(): string
    {
        // A string, but it has to be an integer written as one — cart and
        // order lines store item_id as an int and cast on the way in.
        return (string) $this->id;
    }

    public function getTitle(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getThumbnailSource(): string { return $this->photo_url; }

    /** In CENTS. €12.50 is 1250. See docs/money.md. */
    public function getPrice(): int { return $this->price_in_cents; }
}
```

Stock and tax are **capabilities**, not requirements. Implement
[`HasStockInterface`](stock.md), [`TaxableInterface`](tax.md), both, or
neither; a buyable that implements neither is a complete buyable that is always
addable and contributes no tax.

### If your prices are not already in cents

They very often are not — a `decimal(10,2)` of euros is the common dry3
shape. Convert at the boundary, in `getPrice()`, and only there:

```php
public function getPrice(): int
{
    return (int) round((float) $this->price * 100);
}
```

One multiplication on one value, rounded immediately, is safe. What is not safe
is holding money in a float and adding it up — which is the whole argument in
[Money](money.md). The real fix is a `bigint` column of cents.

## Checking it works

```php
$cart = $app->get(\Tnt\Ecommerce\Contracts\CartInterface::class);

$cart->add($product, 3);

$cart->getSubTotal();   // cents
$cart->getTotal();      // cents
$cart->getTax();        // cents — 0 unless the buyable is Taxable
```

Outside a web request — a console command, a test — the session-backed defaults
have nothing to key on. Build the cart by hand instead:

```php
use Tnt\Ecommerce\Cart\Cart;
use Tnt\Ecommerce\Cart\InMemoryCartStorage;
use Tnt\Ecommerce\Account\GuestUserResolver;
use Tnt\Ecommerce\Tax\TaxPolicy;
use Tnt\Ecommerce\Tax\PriceConvention;

$cart = new Cart(
    $app->get(ShopInterface::class),
    new InMemoryCartStorage(),
    $app->get(PaymentInterface::class),
    new GuestUserResolver(),
    new TaxPolicy(PriceConvention::Inclusive, 0)
);
```

`GuestUserResolver` rather than the configured resolver is not just a
simplification: `AccountsUserResolver` reads the session unconditionally, and
with no session started Oak's file session handler fails on a null id. A
checkout cannot currently run in a cron or a queue worker with accounts
configured.

## Next

- [Cart](cart.md) — what a cart is and what it adds up
- [Buyable](buyable.md) — the contract and the two capabilities
- [Money](money.md) — cents, and the rounding rule everything obeys
- [Customer](customer.md) — guests, accounts, and the dry-accounts pairing
- [Tax](tax.md) — rates, and whether your prices include them
