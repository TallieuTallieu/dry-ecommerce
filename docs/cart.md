# Cart

```php
<?php

$cart = $app->get(CartInterface::class);

$cart->add($buyable, 2);
$cart->add($buyable, 1, ['cheese' => 'no goat']); // a second line — see below
$cart->remove($buyable); // removes every line of the buyable
$cart->clear();
$items = $cart->items();

$itemId = $items[0]->getId(); // the line's own id — what a basket form round-trips
$cart->updateQuantity($itemId, 3); // set the line to 3; zero or less removes it
$cart->removeItem($itemId); // remove exactly this line

$cart->setFulfillment($shipping);
$fulfillment = $cart->getFulfillment();
$fulfillmentCost = $cart->getFulfillmentCost();

$cart->addDiscount($discountCode);
$discountCode = $cart->getDiscount();

$subTotal = $cart->getSubTotal();
$total = $cart->getTotal();
$reduction = $cart->getReduction();
$tax = $cart->getTax();

$order = $cart->checkout($customer); // or checkout() — a guest, no customer row
$order = $cart->place($draft); // place an existing draft — see docs/orders.md
```

`add()` adds what you ask it to. **Stock does not veto it**, and `canAdd()` is how
you ask:

```php
if (!$cart->canAdd($buyable, 2)) {
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

## Lines merge on buyable _and_ options

A line is `(buyable, options)`. Adding the same buyable with the same options
merges into the existing line; a different selection is a different line; no
options merges with the no-options line exactly as before options existed.
Options are compared canonically, so the order the choices were assembled in
does not matter. See [Options](options.md) for the whole design.

Because one buyable can now sit on several lines, three operations name their
target differently:

| Call                          | Names           | Does                                                           |
| ----------------------------- | --------------- | -------------------------------------------------------------- |
| `remove($buyable)`            | the buyable     | removes **every** line of it — a buyable cannot name a variant |
| `updateQuantity($itemId, $n)` | one line, by id | sets it to `$n`; `$n <= 0` removes it                          |
| `removeItem($itemId)`         | one line, by id | removes exactly that line                                      |

The id is `CartItemInterface::getId()` — an opaque storage-issued token (the
row id, for the session-backed storage). Both by-id calls **no-op on an
unknown id**: a stale basket form is ordinary, not an error.

`canAdd()` checks stock against the buyable's total across all of its
option-variants — stock counts tapas, not selections of tapas.

## Where the cart lives

The cart's contents are behind `CartStorageInterface`, and the provider binds
one of two row-backed storages — both keep the cart in `ecommerce_cart` and
its lines in `ecommerce_cart_item`; they differ only in how the visitor's row
is found again:

|                                | Row found by                            | Survives                       |
| ------------------------------ | --------------------------------------- | ------------------------------ |
| `SessionCartStorage` (default) | its id, kept in the session             | the session                    |
| `CookieCartStorage`            | its `token`, kept in a dedicated cookie | `ecommerce.cart_lifetime` days |

### The cookie cart

Set a lifetime and the cookie cart is bound instead:

```php
// config/ecommerce.php
'cart_lifetime' => 30,   // days, int — unset keeps the session cart
```

The cookie holds the cart row's **token** — `bin2hex(random_bytes(16))`,
minted at row creation — and never the row id, which is guessable and would
let a visitor walk other people's baskets by counting. Anything in the cookie
that does not look like a token is ignored, and a token pointing at a missing
**or soft-deleted** cart reads as no cart at all: the visitor starts fresh,
which is the honest answer for a reaped or spent basket.

The same lifetime is what the [draft reaper](orders.md#the-reaper) measures
abandonment against — one knob, two consumers, deliberately.

## The cart→order link

`ecommerce_cart.order` is a nullable foreign key to the order the cart is (or
became). The package writes it at [placement](orders.md#the-place-step); a
project MAY write it earlier — at draft creation, through
`CartStorageInterface::setOrderId()` — and the placement write is then just
the same value again. It is what the `Paid` listener follows back to
[soft-delete the cart](#the-carts-afterlife), and it is provenance: a
soft-deleted cart keeps its order link forever.

The key is `ON DELETE SET NULL`: reaping a draft order must not take a living
basket with it.

## The cart's afterlife

`checkout()`/`place()` deliberately leave the cart standing. With an
asynchronous gateway the visitor may come back from a failed or canceled
payment, and their basket must still be there — deleting it at placement is
only correct while payment is synchronous.

The moment the basket is finally spent is **Paid**: the provider's listener
follows the order link and writes `ecommerce_cart.deleted = time()`. That is
a soft delete — the row stays, pointing at its order — and both storages
treat a soft-deleted cart as absent everywhere, so the next visit starts an
empty cart while the old row keeps its history.

`clear()` keeps its old meaning: the **explicit** call hard-deletes the row
and its lines, exactly as before. The soft path belongs to the Paid listener
alone. A synchronous shop that calls `$cart->clear()` after checkout keeps
working; with the Paid listener it has merely become unnecessary.

## Fulfillment attributes live on the cart

The answers a fulfillment method collects — a delivery date, a timeslot —
used to sit in the session under one key. The default
`AttributeStorageInterface` binding is now `CartAttributeStorage`, which
keeps the whole bag as JSON on the visitor's cart row
(`ecommerce_cart.fulfillment_attributes`, canonical `LineOptions` encoding —
one JSON convention per package). The answers live exactly as long as the
basket they belong to: they survive the session with a cookie cart, and they
die with the cart instead of leaking into the next visitor's order.

Nothing about the freeze path changed: placing still reads the method's
required attributes through `getAttribute()` and copies them onto the order.
`SessionAttributeStorage` still exists for a shop that binds it back.

## The storage contract

`CartStorageInterface` is the seam between the cart's arithmetic and wherever
the contents live. Besides the line operations above it carries:

```php
public function getOrderId(): ?int;                       // the cart→order link
public function setOrderId(?int $id): void;
public function getFulfillmentAttributes(): array;        // the whole bag
public function setFulfillmentAttributes(array $attributes): void;
```

Writing either with no cart row yet **creates** one — the same rule as
`add()`. A shop with its own storage implementation adds these four on
upgrade; see [What changed from 1.x](from-1x.md).

`InMemoryCartStorage` implements the whole contract in an array, which is
what lets everything above run in a unit test with no session, cookie or
database anywhere near it.
