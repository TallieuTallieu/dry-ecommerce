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
user_resolver | \Tnt\Ecommerce\Account\GuestUserResolver::class
prices | `inclusive`
delivery_tax_rate | *(none — delivery is untaxed)*

**Careful!** Payment can be set from configuration. the default value of the "payment" config property provides a default NullPayment which basically gives everything away for free. For more info on payments check out the topic payments below.

`user_resolver` decides whether a checkout can be linked to an account. The
default answers "nobody is signed in", so every checkout is a guest checkout.
See [Customer](#customer).

#### Concepts
* Money
* Buyable
* Cart
* Discount & Coupon
* Fulfillment
* Customer
* Address
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

`Money::lineTotal()` is the other operation on money, and the one every cart
line goes through:

```php
Money::lineTotal(1250, 3); // three at €12.50 -> 3750
```

Rates are honoured to four decimal places of a percent, so `21`, `21.5` and
`0.0625` all land where they should.

#### Showing an amount

`Money::toDecimal()` writes cents out in units, exactly:

```php
Money::toDecimal(1225); // '12.25'
Money::toDecimal(5); // '0.05'
Money::toDecimal(-1225); // '-12.25'
```

Two decimal places always, a full stop between them, a `-` in front of a
negative amount, and no thousands separator. It returns a `string`, so the
whole range of an `int` survives it.

**It is not a currency format, and it is not meant to become one.** There is no
symbol, no comma for the decimal point and no locale, because those differ per
shop and per template, and supporting them would pull `ext-intl` into a package
that does not otherwise need it. Displaying an amount is the project's job.

What `toDecimal()` is for is getting out of cents without a `float` doing it.
`$cents / 100` is the thing it replaces — that one expression puts back, on the
very last step, the representation the rest of this package works to keep out.

#### Reading an amount in

`Money::fromDecimal()` is the same boundary the other way, and the more
important of the two. Money leaves this package as a figure on a page, but it
*enters* it as text — an admin field, a config value, a price import — and that
is where a wrong amount gets in.

```php
Money::fromDecimal('12.25'); // 1225
Money::fromDecimal('12.5'); // 1250, the way a person types it
Money::fromDecimal('12'); // 1200
Money::fromDecimal('  -0.05  '); // -5, space ignored
```

Anything else raises `Tnt\Ecommerce\NotAnAmount`, for one of three reasons:

| Text | Why it is refused |
|---|---|
| `''`, `'abc'`, `'12.2.5'`, `'1e3'` | Not an amount. A plain `(int)` cast reads every one of these as `0`, and `0` is a believable price. |
| `'12.255'` | Finer than a cent. `Money` could round it and will not: that changes a price nobody asked to change. Round it where the extra precision came from. |
| `'92233720368547758.08'` | In cents, past what a PHP `int` holds. |

`'12,25'`, `'1,234.56'` and `'€ 12,25'` are refused too, symmetrically with
`toDecimal()` emitting none of those. Whatever formats an amount for a person
is what unformats it again.

The pair round-trips exactly, across the whole range of an `int`:

```php
Money::fromDecimal(Money::toDecimal($cents)) === $cents; // always
```

#### Where the exactness stops

Integer cents are exact over a range, not everywhere, and `Money` refuses the
two ways out of that range rather than answering approximately:

| Raises | When |
|---|---|
| `Tnt\Ecommerce\AmountTooLarge` | The amount is past the ceiling for its rate. The amount is multiplied twice on the way to an answer — once by the rate, once by 2 to round the half — so at 21% the largest exact amount is 219,604,096,115,589,897 cents. `getMaximumAmount()` reports the ceiling for the rate that was used. |
| `Tnt\Ecommerce\UnsupportedRate` | The rate is finer than `0.0001%`, or is too large to scale, or is `INF` or `NAN`. A rate of exactly `0` is fine and takes nothing off. |

Both extend `InvalidArgumentException`, so one `catch` covers the pair.

Neither is reachable with a real order at a real VAT rate — the ceiling is
around €2.19 quadrillion. They are reachable by passing something that is not
cents, or not a percentage, which is when an exception is worth more than an
amount. Before these existed, an amount over the ceiling raised a `TypeError`
from inside `intdiv()`, and a rate of `0.000004`, `INF` or `NAN` quietly
returned `0` cents behind a PHP warning.

### Buyable

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

#### About the id

`getId()` returns a `string`, but the value has to be an integer written as one.
Cart lines and order lines reference their buyable by class name plus `item_id`,
and both `item_id` columns are `int(11)`; a buyable answering `'sku-a'` would be
stored, and read back, as item 0. A model returns `(string) $this->id`.

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
$tax = $cart->getTax();

$order = $cart->checkout($customer);
```

`add()` adds what you ask it to. **Stock does not veto it**, and `canAdd()` is how
you ask:

```php
if (! $cart->canAdd($buyable, 2)) {
    // out of stock, or not stocked at all — your call what happens next
}

$cart->add($buyable, 2); // adds either way
```

That split is deliberate. Whether a shop refuses a sale it cannot fill today,
takes it as a backorder, or oversells on purpose and reconciles later is trade
policy, and two shops selling the same thing answer it differently. This package
does not know which shop it is in, so it reports what the stock says and leaves
the decision where the knowledge to make it lives.

`Cart::getTax()` draws the same line, for the same reason.

### Discount & Coupon
Documentation coming soon

### Fulfillment
Documentation coming soon

### Customer

A customer row records who placed *one* order and where it was going. Every
checkout writes a new one, and an order carries a non-null `customer` either
way — guest checkout and account checkout are the same code path.

Rows are never reused or deduplicated. **An email address is not an identity
claim** — matching a checkout to an existing row by email would let anyone check
out as somebody else's address and merge into their record.
`CustomerRepository::byEmail()` exists so an admin can *find* the orders placed
from an address; checkout does not use it.

`CustomerInterface` is three getters — `getFirstName()`, `getLastName()`,
`getEmail()` — and that is everything a checkout has to be able to answer about
who placed an order. Addresses are asked for separately; see
[Address](#address).

#### Linking a customer to an account

`Customer.user` is a nullable id: the account that was signed in at checkout, or
`NULL` for a guest. It is what lets a shop show a person their orders without
this package and an accounts package keeping two unrelated records of the same
person.

The pairing with [dry-accounts](https://github.com/TallieuTallieu/dry-accounts)
is one config line:

```php
// config/ecommerce.php
'user_resolver' => \Tnt\Ecommerce\Account\AccountsUserResolver::class,
```

That is all. `Cart::checkout()` asks the resolver who is signed in and the
customer row records the answer; nothing else in the checkout changes, and a
guest checkout costs no extra query.

dry-accounts is **not** a dependency of this package — a shop that sells without
accounts installs and runs exactly as before. Anything else implementing
`UserResolverInterface` works just as well:

```php
final class MyAuthResolver implements \Tnt\Ecommerce\Contracts\UserResolverInterface
{
    public function getCurrentUserId(): ?int
    {
        return MyAuth::user()?->id;
    }
}
```

A returning account gets a *new* customer row linked to the same user. To read
the account back:

```php
$userId = $order->getCustomer()->getUserId(); // int|null

if ($userId !== null) {
    $user = \Tnt\Account\Model\User::load($userId);
}
```

The id is deliberately not hydrated into a user object by this package. Doing
that would mean naming a user class it does not own, and a project that has
accounts has that class imported already.

##### The column has no foreign key

`ecommerce_customer.user` is the one relation here without a database-level
constraint. The table it would point at belongs to dry-accounts, and a shop
without that package has no such table — MySQL refuses a constraint against a
table that is not there, so declaring one would break the `ecommerce` migrator
on exactly the shops the nullable column exists to support. The two packages
also register separate migrators with no ordering between them, so there is no
point at which the target is known to exist. Add the constraint in your own
schema if your shop always has both.

### Address

A customer keeps an **address book**: `ecommerce_address`, one row per address,
as many as they have, each with an `AddressType` saying what it is for.

```php
use Tnt\Ecommerce\Address\AddressType;
use Tnt\Ecommerce\Model\Address;

$address = new Address();
$address->customer = $customer;
$address->setType(AddressType::Shipping);
$address->first_name = 'Ada';
$address->last_name = 'Lovelace';
$address->street = 'Kortrijksesteenweg';
$address->number = '1144';
$address->postal_code = '9051';
$address->city = 'Gent';
$address->country = 'BE';
$address->save();
```

`AddressType` has two cases, `Billing` and `Shipping`, because those are the two
questions a shop asks of an address: where the invoice goes and where the parcel
goes. A "home" or "work" label is a shop's own vocabulary for its customers'
addresses and means nothing to a checkout, so it is not modelled here.

The recipient's name lives on the address, not on the customer. A parcel can go
to somebody else — a gift, a colleague, a neighbour who is in during the day.

#### Reading the book

```php
$customer->getAddresses();                        // iterable<Address>
$customer->getAddress(AddressType::Shipping);     // ?AddressInterface
```

`getAddress()` answers with the **most recently added** address of that kind.
That is a default for the shop that never asks, not a rule. A shop that lets a
customer pick at checkout says which one it picked:

```php
$customer->useAddress($chosen);   // for this request; nothing is written
$cart->checkout($customer);
```

A customer with no address of that kind answers `null`, and nothing is
substituted from the other kind — see below.

#### The order takes a copy

**An order never points at the address book.** At checkout,
`Order::freezeCustomer()` copies the identity and both addresses onto the
order's own columns:

```
first_name  last_name  email
billing_first_name  billing_last_name  billing_street  billing_number
billing_postal_code  billing_city  billing_country
shipping_… (the same seven)
```

The reason is that an address book is *edited*. A customer moves house and
corrects the address on file; a customer deletes an address they used once. Both
are things a book must let them do, and an order that read through to those rows
would answer, next year, that last year's parcel went somewhere it never went —
or that it went nowhere, the row having been deleted. **An invoice is a
statement about the past, and a mutable row cannot back one.**

`Order.customer` is still a foreign key, and it still answers "whose account is
this order on". Only the frozen copy answers "who placed it and where did it
go", and only the frozen copy is safe to print.

```php
$order->getFirstName();        // as it was at checkout
$order->getEmail();
$order->getShippingAddress();  // Tnt\Ecommerce\Address\FrozenAddress
$order->getBillingAddress();
```

`FrozenAddress` implements the same `AddressInterface` as `Address`, so one
template renders either, but it is readonly and has nothing behind it to
re-read. `isEmpty()` asks whether the order recorded an address of that kind at
all.

#### Nothing is substituted for a missing address

A customer with a shipping address and no billing address freezes seven blank
billing columns, and vice versa. An order carrying an address the customer never
gave for that purpose is a worse record than one that admits the purpose had no
address — and it is a record nobody can correct afterwards, because it looks
exactly like an address that *was* given.

A shop that means "bill me where you ship" says so by keeping an address of each
kind, which is precisely the thing the book can now express and the twelve
inline columns could not.

#### Customers that keep no addresses

`HasAddressesInterface` is a capability, in the same shape as
`HasStockInterface` and `TaxableInterface`: `Cart::checkout()` asks with
`instanceof`, and a customer that does not implement it is not asked. A shop
selling downloads, or one with its own customer class, freezes both sides blank
and checks out exactly as before.

#### Upgrading an existing shop

> **This one needs SQL by hand.** Oak's migrator is a positional version
> counter: it remembers *how many* revisions a shop has run, not which, and it
> never re-runs one it has already applied. `CreateAddressTable` is appended to
> the end of the list, so running your project's migrate command creates
> `ecommerce_address` for you. The edits to `CreateCustomerTable` and
> `CreateOrderTable` are for fresh installs only and will not reach a shop that
> already has those tables.

Run the migrator first, so `ecommerce_address` exists. Then these four steps, in
order, having backed up — step 4 destroys the data steps 2 and 3 move.

**1. Add the frozen columns to `ecommerce_order`.**

```sql
ALTER TABLE `ecommerce_order`
    ADD COLUMN `first_name`           VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `last_name`            VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `email`                VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `billing_first_name`   VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `billing_last_name`    VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `billing_street`       VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `billing_number`       VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `billing_postal_code`  VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `billing_city`         VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `billing_country`      VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `shipping_first_name`  VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `shipping_last_name`   VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `shipping_street`      VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `shipping_number`      VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `shipping_postal_code` VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `shipping_city`        VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `shipping_country`     VARCHAR(255) NOT NULL DEFAULT '';
```

**2. Backfill every existing order from the customer row it points at.** This is
the step that matters: it is the last moment at which the old columns still say
where each order actually went.

```sql
UPDATE `ecommerce_order` o
JOIN `ecommerce_customer` c ON c.id = o.customer
SET o.first_name           = c.first_name,
    o.last_name            = c.last_name,
    o.email                = c.email,
    o.billing_first_name   = c.first_name,
    o.billing_last_name    = c.last_name,
    o.billing_street       = c.address_street,
    o.billing_number       = c.address_number,
    o.billing_postal_code  = c.address_postal_code,
    o.billing_city         = c.address_city,
    o.billing_country      = c.address_country,
    o.shipping_first_name  = c.shipping_first_name,
    o.shipping_last_name   = c.shipping_last_name,
    o.shipping_street      = c.shipping_street,
    o.shipping_number      = c.shipping_number,
    o.shipping_postal_code = c.shipping_postal_code,
    o.shipping_city        = c.shipping_city,
    o.shipping_country     = c.shipping_country;
```

The old billing columns carried no name of their own, so the customer's name is
used for `billing_*`. If your shop leaves `shipping_*` blank to mean "same as
billing", say so here — that convention was yours, and this is where to record
it, because after step 4 there is nothing left to say it with:

```sql
UPDATE `ecommerce_order`
SET shipping_first_name  = billing_first_name,
    shipping_last_name   = billing_last_name,
    shipping_street      = billing_street,
    shipping_number      = billing_number,
    shipping_postal_code = billing_postal_code,
    shipping_city        = billing_city,
    shipping_country     = billing_country
WHERE shipping_street = '' AND shipping_city = '';
```

**3. Seed the address book from the customer rows.** Optional — orders no longer
need it — but it is what gives returning customers something to pick from.

```sql
INSERT INTO `ecommerce_address`
    (created, updated, customer, type,
     first_name, last_name, street, number, postal_code, city, country)
SELECT UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), c.id, 'billing',
       c.first_name, c.last_name, c.address_street, c.address_number,
       c.address_postal_code, c.address_city, c.address_country
FROM `ecommerce_customer` c
WHERE c.address_street <> '';

INSERT INTO `ecommerce_address`
    (created, updated, customer, type,
     first_name, last_name, street, number, postal_code, city, country)
SELECT UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), c.id, 'shipping',
       c.shipping_first_name, c.shipping_last_name, c.shipping_street,
       c.shipping_number, c.shipping_postal_code, c.shipping_city,
       c.shipping_country
FROM `ecommerce_customer` c
WHERE c.shipping_street <> '';
```

Note that a customer row here is still one-per-checkout, so this produces one
address per past order. Deduplicate afterwards if you care to.

**4. Drop the twelve inline columns.**

```sql
ALTER TABLE `ecommerce_customer`
    DROP COLUMN `address_street`,
    DROP COLUMN `address_number`,
    DROP COLUMN `address_postal_code`,
    DROP COLUMN `address_city`,
    DROP COLUMN `address_country`,
    DROP COLUMN `shipping_first_name`,
    DROP COLUMN `shipping_last_name`,
    DROP COLUMN `shipping_street`,
    DROP COLUMN `shipping_number`,
    DROP COLUMN `shipping_postal_code`,
    DROP COLUMN `shipping_city`,
    DROP COLUMN `shipping_country`;
```

`Customer` no longer declares any of them, so anything in your project reading
`$customer->address_street` now reads `null`. Grep for the prefixes
`address_` and `shipping_` before you deploy; on a placed order the replacement
is `$order->getBillingAddress()` or `$order->getShippingAddress()`, and on a live
customer it is `$customer->getAddress(AddressType::Billing)`.

**One more breaking change, without SQL.** `CustomerInterface` now declares
`getEmail(): string`. If you implement that interface yourself rather than
extending `Tnt\Ecommerce\Model\Customer`, add the method.

### Order
Documentation coming soon

#### The order reference

`Order.order_id` is the reference a customer quotes when they mean an order —
the row id, a dash, then ten characters:

```
12-K4M7QX9RTB
```

Find an order by it with `OrderRepository::byOrderId()`. The row id keeps it
unique without a lookup, and the random part is drawn with `random_int()` so
that a shop's orders cannot be walked through one after another.

> [!important] It is a reference, not a credential
> Knowing an order reference must **not** be enough to see the order. It is
> unguessable to stop enumeration, not to stand in for signing in. A page that
> shows an order still has to establish who is asking, exactly as it would if
> the reference were `1`, `2`, `3` — otherwise every email that quotes it
> becomes a key to the order it names.
>
> If you want a "track your order" link with nothing behind it, that is a
> separate secret token, and it is yours to add.

The alphabet leaves out `I`, `L`, `O` and `U`: a reference gets read down a
telephone and copied off a printed invoice, so a `0` that might be an `O` costs
somebody a support call, and dropping `U` keeps a random string from
occasionally spelling something a customer would rather not read out.

### Payment
Documentation coming soon.

Available payment packages:
* Mollie: https://github.com/reinvanoyen/dry-mollie

### Stock

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

#### Taking out more than there is

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

### Tax

Optional. A buyable that carries tax implements `TaxableInterface` and returns a
rate; one that does not contributes no tax and is asked nothing.

#### A rate states the rate

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

#### Do your prices include tax?

**This is the one thing you have to tell the package**, because it cannot be
inferred and everything else follows from it. Set `ecommerce.prices` to
`inclusive` or `exclusive`.

A price of `1250` at 21% is either €12.50 *of which* €2.17 is VAT, or €12.50
*plus* €2.63 of VAT. The two differ by the whole tax amount:

| | `inclusive` | `exclusive` |
|---|---|---|
| subtotal | 2500 | 2500 |
| VAT | *434, contained* | **526, added** |
| delivery | 475 | 475 |
| **total** | **2975** | **3501** |

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

#### The coupon reduces what is taxed

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
rule](#the-rounding-rule).

#### Delivery

Set `ecommerce.delivery_tax_rate` to a percentage and the fulfillment cost is
taxed at it. Leave it unset and delivery is untaxed — which is different from
setting it to `0`, a zero-rated supply that prints as one.

> [!warning] One rate for the whole shop
> Belgian VAT treats delivery as ancillary to what is being delivered, so a
> cart of 6% goods should carry 6% on its delivery and a mixed cart should
> apportion it across the rates in it. A single shop-wide rate reports the same
> figure whatever is in the cart: exact for a shop selling at one rate,
> approximate for a shop selling at several. Delivery is small beside the
> goods, so the error is small — but if you cannot accept it, this package does
> not apportion delivery and you will need to.
