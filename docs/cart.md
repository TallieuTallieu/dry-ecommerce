# Cart

```php
<?php

$cart = $app->get(CartInterface::class);

$cart->add($buyable, 2);
$cart->add($buyable, 1, ['cheese' => 'no goat']);   // a second line — see below
$cart->remove($buyable);          // removes every line of the buyable
$cart->clear();
$items = $cart->items();

$itemId = $items[0]->getId();     // the line's own id — what a basket form round-trips
$cart->updateQuantity($itemId, 3);   // set the line to 3; zero or less removes it
$cart->removeItem($itemId);          // remove exactly this line

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

## Lines merge on buyable *and* options

A line is `(buyable, options)`. Adding the same buyable with the same options
merges into the existing line; a different selection is a different line; no
options merges with the no-options line exactly as before options existed.
Options are compared canonically, so the order the choices were assembled in
does not matter. See [Options](options.md) for the whole design.

Because one buyable can now sit on several lines, three operations name their
target differently:

| Call | Names | Does |
| --- | --- | --- |
| `remove($buyable)` | the buyable | removes **every** line of it — a buyable cannot name a variant |
| `updateQuantity($itemId, $n)` | one line, by id | sets it to `$n`; `$n <= 0` removes it |
| `removeItem($itemId)` | one line, by id | removes exactly that line |

The id is `CartItemInterface::getId()` — an opaque storage-issued token (the
row id, for the session-backed storage). Both by-id calls **no-op on an
unknown id**: a stale basket form is ordinary, not an error.

`canAdd()` checks stock against the buyable's total across all of its
option-variants — stock counts tapas, not selections of tapas.

