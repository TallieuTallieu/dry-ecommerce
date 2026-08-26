# Options and variants

A cart line carries the choices it was added with — a size, a preference, "no
goat's cheese" — as a first-class part of the line. Options participate in the
merge key, ride through checkout, and are frozen onto the order line. This
page is how they work and what they deliberately do not do.

## What a cart line is

A line is identified by four things:

```
(cart, item_class, item_id, options)
```

The options are the third argument to `add()`, defaulting to none:

```php
public function add(BuyableInterface $buyable, int $quantity = 1, array $options = []);
```

Two adds merge into one line when **all** of buyable class, buyable id and
options match. So:

```php
$cart->add($tapas, 1, ['cheese' => 'no goat']);
$cart->add($tapas, 1, ['cheese' => 'no blue']);

count($cart->items());   // 2 — two selections, two lines

$cart->add($tapas, 1, ['cheese' => 'no goat']);

count($cart->items());               // still 2
$cart->items()[0]->getQuantity();    // 2 — same selection, same line
```

And a caller that passes no options behaves exactly as it always did: no
options is one variant like any other, and it merges with the no-options line.

## The canonical form

Options are compared — and stored — in a canonical encoding:
`Tnt\Ecommerce\Cart\LineOptions` sorts every associative level by key and
JSON-encodes the result, so the same selection assembled in a different order
is the same line:

```php
$cart->add($thing, 1, ['size' => 'L', 'gift' => true]);
$cart->add($thing, 1, ['gift' => true, 'size' => 'L']);   // merges
```

Three consequences worth knowing:

- **Empty is `NULL`, not `'[]'`.** A line added without options stores `NULL`
  in the `options` column — which is also what every line from before the
  column existed holds, so old lines and new no-options lines merge as the
  same absence of choices.
- **Keys are sorted; list values are not.** For a list the order *is* the
  value — a ranking, a sequence of steps — and the package cannot tell a set
  from a sequence. A shop that means "a set of ticked boxes" keys the array
  (`['no_goat' => true]`) or sorts the list itself before handing it over.
- **Options come back canonical.** `getOptions()` decodes the stored string,
  so keys read back sorted, not in the order the caller assembled them.

Validate against the product, not the post: options arrive from a form, and a
preference id belonging to another product has no business being frozen onto
an order. That filtering is the shop's, before `add()`.

## Line identity: `updateQuantity()` and `removeItem()`

With options in the merge key, one buyable can sit on several lines — so the
buyable stops naming a line, and the line's own id becomes the handle:

```php
foreach ($cart->items() as $item) {
    $item->getId();        // the line's id — put this in the basket form
    $item->getOptions();   // ['cheese' => 'no goat'], or []
}

$cart->updateQuantity($itemId, 3);   // set the line to 3
$cart->updateQuantity($itemId, 0);   // zero or less removes the line
$cart->removeItem($itemId);          // remove exactly this line
```

The id is an opaque token the storage issued (the row id, for the shipped
storage) — a basket form round-trips it instead of a class name, which
removes the whitelist-or-object-injection choice every shop used to face.

Two rules, both deliberate:

- **An unknown id is a no-op.** A stale basket form — the line was removed in
  another tab, the cart was checked out — is ordinary, not an error.
- **`remove($buyable)` removes every variant.** A caller holding only a
  buyable cannot say which variant it means, so anything less would remove an
  arbitrary one. A caller that means one line says so by id.

## Stock counts the buyable, not the selection

`canAdd()` checks stock against the *total* the cart holds of a buyable,
summed across all of its option-variants: three tapas without goat's cheese
and two without blue cheese are five tapas out of the same stock. That is
`CartStorageInterface::quantityOf()`'s contract, and the reason it is not
"the quantity of the line `add()` would merge into".

## Checkout freezes the options onto the order line

`Order::add()` copies the canonical options onto
`ecommerce_order_item.options`, next to the price it already froze, and
`OrderItemInterface::getOptions()` reads them back:

```php
$order = $cart->checkout($customer);

foreach ($order->getItems() as $line) {
    $line->getPrice();     // the frozen line total, in cents
    $line->getOptions();   // the frozen selection, or []
}
```

Same reasoning as `Order::freezeCustomer()`: the cart row dies with the
checkout and the buyable never knew what was chosen, so the order's own
column is the only place "what was ordered" can still be read next year. A
line placed before options existed reads back `[]`.

## What options are not: a price

Options do not price themselves. The line's price is still
`quantity × BuyableInterface::getPrice()`, and nothing in the package reads
the options to adjust it. A shop whose options change the price prices them
itself — a configuration model with its own frozen price, or a buyable per
variant — exactly as before. Options carry *what was chosen*; what that
choice costs is the shop's arithmetic.

### The configuration-model workaround is obsolete — mostly

Before options existed, the only way to keep two selections on two lines was
to make the configuration *be* the buyable: a `ProductConfiguration` table
with a fingerprint column, find-or-create, append-only forever. That whole
apparatus is now unnecessary for a shop whose options are **choices only** —
pass them as options and delete the table.

It remains the right shape for a shop whose options **change the price**: the
configuration row is where the computed price is frozen, and
`getPrice()` reads it. The difference is that such a shop now keeps the table
for pricing alone, not for line identity.

## One rate per line is the design

`TaxableInterface` gives a buyable exactly **one** `TaxRateInterface`, and
that is deliberate: one line, one rate, and the line's VAT figure is printable
on its own. The contract is not growing a second rate, and options do not
change that.

What follows: an add-on taxed differently from the product it decorates — a
21% greeting card on a 6% platter — is the **shop's** job to model as its own
cart line, so each line carries its own rate and the sum of the printed lines
stays exact. "Adding one product adds several lines" is an ordinary shape for
a cart, not a workaround. Options describe a line; they do not split one.

## See also

- [Cart](cart.md) — the full cart surface, including the by-id operations
- [Orders](orders.md) — what a line freezes at checkout
- [Buyable](buyable.md) — the contract, untouched by options
- [Tax](tax.md) — why one rate per line is the binding constraint
