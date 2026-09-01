# What changed from 1.x

The domain moved substantially between the 1.x line and this one. If you are
looking at an older project — `reinvanoyen/dry-ecommerce: ^1.0` in its
`composer.json` — most of what the rest of these docs says does not describe it.

This page is for reading old code, not a migration script. There is no upgrade
path that does not touch the project's own models.

## Money stopped being a decimal

**Then:** `decimal(10,2)` columns, floats in PHP, euros.

**Now:** `int` cents everywhere — in the contracts, the models and the columns.
`ecommerce_order.{total,subtotal,reduction,fulfillment_cost}` and
`ecommerce_order_item.price` are `bigint`.

€12.50 is `1250`. `BuyableInterface::getPrice()` returns an `int`.

The reason is accumulation: the package adds money up in loops, and float
accumulation drifts. `bigint` rather than `int` because PHP's integer is signed
64-bit and `int(11)` would have stopped at €21,474,836.47 — _less_ range than
the `decimal(10,2)` it replaced.

A project moving over converts its price columns, or converts in `getPrice()`.
See [Money](money.md) and [Installation](installation.md#if-your-prices-are-not-already-in-cents).

## `BuyableInterface` lost two methods

**Then:** every buyable had to answer five things plus two more:

```php
public function getStockWorker(): StockWorkerInterface;
public function getTaxRate(): TaxRateInterface;
```

Both mandatory — so a model with no stock concept and no VAT rate of its own
could not be sold without first inventing answers. The package shipped the
inventions itself: a `NullStockWorker` that said everything was always in
stock, and a `NullTaxRate` that taxed nothing.

**Now:** both are gone, and so are the two null classes. Stock and tax are
capabilities a buyable opts into, one interface each:

```php
interface HasStockInterface
{
    public function getStockWorker(): StockWorkerInterface;
}
interface TaxableInterface extends BuyableInterface
{
    public function getTaxRate(): TaxRateInterface;
}
```

Implement neither, either or both. A buyable implementing neither is complete,
not degenerate: always addable, contributes no tax, and the cart asks it
nothing it cannot answer.

**If you are porting a model:** delete the `NullStockWorker` / `NullTaxRate`
returns and implement nothing in their place. That is the whole change.

## A tax rate states the rate, not the amount

**Then:**

```php
public function getTax(int $amount): int;    // hand it an amount, get the tax
```

**Now:**

```php
public function getPercentage(): int|float;  // 21 means 21%
```

The old shape could not survive tax-inclusive pricing. Inclusive and exclusive
prices need `amount x r/100` and `amount x r/(100 + r)` respectively, and given
only a finished figure the package cannot tell which it was handed, nor recover
the rate once it has been rounded to the cent. Every implementation would have
had to branch on the convention itself and reimplement the same rounding rule.

## Prices now say whether they include tax

New in this line: `ecommerce.prices` is `inclusive` or `exclusive`, and
`TaxPolicy` / `PriceConvention` carry it. An order records the convention it was
placed under, in `ecommerce_order.prices`, so a shop that switches next year can
still reprint old invoices as they were charged.

Under `inclusive` — the Belgian consumer norm, and the default — `getTax()`
reports a figure contained in the total and the total does not move. Under
`exclusive` the tax is added to the total.

Defaults are chosen so an existing shop's totals do not shift: inclusive prices,
and delivery at 0%. See [Tax](tax.md).

## The customer got an address book

**Then:** twelve inline columns on `ecommerce_customer` — `address_*` and
`shipping_*` — giving a customer exactly one billing and one shipping address,
for ever.

**Now:** a one-to-many into `ecommerce_address`, with an `AddressType` of
`Billing` or `Shipping` and a default flag per kind. An address carries no
recipient name — it is purely a _where_, and the identity an order is placed
under is frozen on the order itself. See [Addresses](addresses.md).

Two things follow that used to be the same thing:

- **Editing an address is now safe**, because no order reads through the
  customer row to find out where it was sent.
- **Which address an order uses is now a choice**, because there can be several.
  `Customer::useAddress()` makes it, per checkout, and it is deliberately not a
  column.

## An order freezes its own copy

New, and the reason the change above is safe: `Order::freezeCustomer()` copies
the name, email, company, VAT number and **both addresses** onto the order's own
columns at checkout.

An address book is edited; an invoice is a statement about the past. Editing or
deleting a book entry cannot reach an order already placed. `getBillingAddress()`
and `getShippingAddress()` return a `FrozenAddress` read off those columns, not
the live row.

## Customers: one row per account

**Then:** a row per checkout, with no notion of an account.

**Now:** a shop with a signed-in user checks out into the row already on that
account, so the address book accumulates across their orders. A guest still gets
a fresh row every time.

The asymmetry is deliberate and is not about convenience: an account has been
proved and an email address has only been typed, so recognising a returning
customer by email would let anybody check out into somebody else's address book.

`UserResolverInterface` is the whole of the accounts pairing — one method,
answering an id or null. See [Customer](customer.md).

The invariant is now **schema-enforced and package-served**: `UNIQUE` on
`ecommerce_customer.user` (the `MakeCustomerUserUnique` revision — a shop
holding duplicate rows for one account must merge them by hand before
migrating), `Customer::forUser($userId, $email = '')` as the race-safe
find-or-create, `OrderRepository::forUser($userId)` for an account's order
history in one query, and the opt-in `SyncsCustomer` trait for the project's
account model, after which the **account owns the row's email**. See
[Customer](customer.md#one-row-per-account).

## Cart lines gained per-line options

New in 4.x; the 1.x line has nothing like it. A line used to be
`(cart, item_class, item_id)` and nothing else, so two differently-configured
adds of the same product merged silently into one line with one selection.

Now `add()` takes an options array, the options are part of the merge key —
compared canonically, so key order does not matter — and checkout freezes them
onto the order line's own `options` column, where
`OrderItemInterface::getOptions()` reads them back. Because one buyable can
sit on several lines, lines also have usable identity:
`updateQuantity($itemId, $n)` and `removeItem($itemId)` work on
`CartItemInterface::getId()`, and `remove($buyable)` removes every variant.

Old projects that made the configuration a buyable of its own to keep
selections apart no longer need to — unless the configuration _prices_ the
selection, which is still the shop's job. See [Options](options.md).

### Contract changes at a glance

A project that ships its **own implementation** of any of these contracts has
to follow the changed members on upgrade; a project that only _consumes_ them
is untouched (`add()`'s new parameter defaults to no options, `checkout()`'s
customer stays passable, and callers of the removed address getters read the
identity off the order instead — only code assuming `getCustomer()` is
non-null needs a look).

| Contract               | Changed                                                                                                                                      | New                                                                                                                                                  |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| `CartInterface`        | `add()` gains `array $options = []`; `remove()` now removes every option-variant; `checkout()`'s customer is now `?CustomerInterface = null` | `updateQuantity(string $itemId, int $quantity)`, `removeItem(string $itemId)`, `place(Order $order, ?CustomerInterface $customer = null)`            |
| `CartStorageInterface` | `add()` gains `$options`; `quantityOf()` sums across variants                                                                                | `updateQuantity()`, `removeItem()`, `getOrderId(): ?int`, `setOrderId(?int)`, `getFulfillmentAttributes(): array`, `setFulfillmentAttributes(array)` |
| `CartItemInterface`    | —                                                                                                                                            | `getOptions(): array`                                                                                                                                |
| `OrderInterface`       | `getCustomer()` returns `?CustomerInterface` — null is a guest order                                                                         | —                                                                                                                                                    |
| `OrderItemInterface`   | —                                                                                                                                            | `getPrice(): int`, `getOptions(): array`                                                                                                             |
| `PaymentInterface`     | —                                                                                                                                            | Nothing on it — but 4.x adds the opt-in `PaymentGatewayInterface` (`statusOf()`) and `RedirectorInterface` beside it: the [gateway harness](payment.md#writing-a-gateway) a real provider implements instead of inventing its own webhook plumbing |
| `AddressInterface`     | `getFirstName()` and `getLastName()` **removed** — an address is purely a _where_; the who stays frozen on the order                         | —                                                                                                                                                    |
| `FulfillmentInterface` | —                                                                                                                                            | `attributeOr(string $name, mixed $default): mixed` — the never-throwing peek beside `getAttribute()`'s guarded read; free via `HasFulfillmentAttributes`, a hand-rolled implementation adds one method |

## Orders gained a lifecycle of their own

New in 4.x, alongside the payment status: `ecommerce_order.state` is `draft`
or `placed` (`OrderState`), and `ecommerce_order.customer` is **nullable** —
a guest order carries no customer row at all, and the identity it was placed
under lives on the order's own frozen columns as it always did.

A **draft** is a checkout form in progress persisted as a row: the project
writes columns onto it as the form advances, and `Cart::place($draft)`
freezes it from the cart when the customer accepts — lines copied, money
frozen, reference minted, `Created` dispatched, `pay()` called. A
placed-but-unpaid order can be **re-placed**: same row, lines replaced,
`Created` re-fired; a paid one throws `AlreadyPaid`. Plain `checkout()` is
unchanged — it is now one call into the same place-step with a fresh order.

Every pre-4.x row is backfilled `placed` (and `''` reads as placed either
way); `OrderRepository::placed()` is the scope every list should use, and the
`ecommerce:reap-drafts` command deletes drafts nobody touched within
`ecommerce.cart_lifetime` days. The cart, meanwhile, gained a token (the
[cookie cart](cart.md#the-cookie-cart)), a link to the order it became, and a
soft-delete written when that order is paid. See [Orders](orders.md) and
[Cart](cart.md).

## The cart stopped writing a row just for existing

**Then:** the cart created its `ecommerce_cart` row from its constructor, so
merely resolving the cart out of the container — on any page, for any visitor,
crawlers included — wrote a row and started a session.

**Now:** a row is written only when something is actually put in the cart.
Reading an empty cart costs one lookup and no writes.

The cart also gained `CartStorageInterface`, which is the seam that lets it be
unit-tested: hand it `InMemoryCartStorage` and the whole of its arithmetic runs
with no session and no database.

## Order references are no longer guessable

**Then:** eight characters from `rand()`, split as `rand(5, 8)` and the
remainder.

**Now:** the row id, a dash, and ten characters of `random_int()` over
Crockford's base 32 — `12-K4M7QX9RTB`.

`rand()` is a Mersenne Twister, and its next output can be derived from enough
previous ones, so old references were predictable to anybody who had placed a
few orders. The old split also added no randomness at all — it only moved the
underscore, and left the tail empty about one order in four.

The alphabet drops `I`, `L`, `O` and `U`, because a reference gets read down a
telephone.

> A reference is still **not** a credential. It is unguessable so a shop's
> orders cannot be walked one after another, not so it can stand in for signing
> in. A page showing an order must still establish who is asking.

## Namespace and package name

**Then:** `reinvanoyen/dry-ecommerce`, and the 1.x tags.

**Now:** `tallieutallieu/dry-ecommerce`. The PHP namespace `Tnt\Ecommerce\` is
unchanged, which is why a lot of old code looks like it should still work.

## See also

- [Installation](installation.md) — the current setup, start to finish
- [Buyable](buyable.md) — the slimmed contract
- [Tax](tax.md) — the convention that drove most of these changes
