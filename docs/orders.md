# Orders

What `Cart::checkout()` writes, what it freezes, and what it deliberately does
not.

An order is a statement about the past. Most of the design below follows from
that one sentence: an invoice may not change because somebody edited their
address book, renamed their company, or switched the shop's pricing convention.

## What checkout writes

In this order, in `Cart::checkout()`:

1. `Customer::linkTo($userId)` — the account link, **before** the order exists,
   because the order points at the customer row and that row has to be finished
   by then.
2. The money: `total`, `subtotal`, `reduction`, `fulfillment_cost`, `tax`.
3. `prices` — the [price convention](tax.md) the order was placed under.
4. `fulfillment_method` — the id, as a string.
5. `fulfillment_attributes` — the order's **own copy** of the method's required
   attributes, as JSON, or null. See below.
6. `payment_status` — `pending`, from birth. A gateway's events move it from
   there; see [Payment](payment.md#the-status-lifecycle).
7. `discount` — the code that was in force, or null.
8. `customer` — the foreign key.
9. `freezeCustomer($customer)` — the order's **own copy** of who placed it.
10. Save, then generate `order_id` and save again.
11. Every cart line, through `Order::add()` — the frozen line total **and the
    line's options**, as the order's own copy. See
    [Order lines](#order-lines).
12. `Created` is dispatched; then `PaymentInterface::pay()`.

## The two records of the customer

Not redundant, and the difference matters:

| | Reads | Use for |
| --- | --- | --- |
| `$order->getCustomer()` | the live `ecommerce_customer` row | "whose account is this order on" |
| `$order->getBillingAddress()` etc. | the order's own frozen columns | anything printed |

`freezeCustomer()` copies the first name, last name, email, company, VAT number
and **both addresses** onto the order's own columns. `getBillingAddress()` and
`getShippingAddress()` hand back a `FrozenAddress` built from those columns —
never the live `Address` row.

So this is safe, and is the point of the whole arrangement:

```php
foreach ($customer->getAddresses() as $address) {
    $address->street = 'Elsewhere';
    $address->save();
}

Order::load($id)->getBillingAddress()->getStreet();   // unchanged
```

A customer with no address of a kind freezes an **empty** snapshot — every
field `''` — rather than borrowing the other kind. Nothing is substituted for a
missing address; see [Addresses](addresses.md#nothing-is-substituted-for-a-missing-address).

## The frozen fulfillment attributes

The same reasoning, applied to the answers a fulfillment method collected — a
delivery date, a timeslot, a pickup point. They live in session storage while
the cart is being filled, and the session dies long before whoever fulfills the
order goes looking for them. So checkout freezes the method's **required**
attributes onto the order's own `fulfillment_attributes` column, as a JSON
object:

```json
{"date": "2026-08-28", "timeslot": 4}
```

Read them back off the order, never through the method:

```php
$order->getFulfillmentAttribute('date');    // '2026-08-28'
$order->getFulfillmentAttribute('absent');  // null
$order->getFulfillmentAttributes();         // ['date' => ..., 'timeslot' => ...]
```

Only the *required* attributes are frozen — they are the answers the method
declared it cannot be fulfilled without. An optional attribute a shop wants on
the order is a required one it has mislabelled. The column stays `NULL` when no
method was chosen or the method requires nothing, and an order from before the
column existed reads back empty.

Two things worth knowing:

- **A missing required attribute stops the checkout.** Freezing reads through
  `getAttribute()`, which throws `MissingAttribute` for a required attribute
  nobody set — an order frozen without the answer its method requires is that
  absence made permanent. Ask `validateAttributes()` before checking out,
  exactly as before.
- **The JSON shape is queryable.** A shop counting pickups per `(date, slot)`
  can query the column directly; the key names are the method's own
  `requireAttributes()` names.

## Payment status

```php
use Tnt\Ecommerce\Payment\PaymentStatus;

$order->getPaymentStatus();    // PaymentStatus::Pending | Paid | Failed | ...
```

Written by the package: `pending` at checkout, and every later value by the
event listeners. An order from before the lifecycle existed — or one carrying a
word this package does not know — reads as `Pending`, the one status that
claims nothing. This is the payment's state, not a fulfillment status. See
[Payment](payment.md#the-status-lifecycle).

## The order reference

`Order.order_id` is the reference a customer quotes — the row id, a dash, then
ten characters:

```
12-K4M7QX9RTB
```

Find one with `OrderRepository::byOrderId()`. The row id keeps it unique without
a lookup; the random part is drawn with `random_int()` so a shop's orders cannot
be walked one after another.

> ### It is a reference, not a credential
>
> Knowing an order reference must **not** be enough to see the order. It is
> unguessable to stop enumeration, not to stand in for signing in. A page that
> shows an order still has to establish who is asking, exactly as it would if
> the reference were `1`, `2`, `3` — otherwise every email quoting it becomes a
> key to the order it names.
>
> For a "track your order" link with nothing behind it, add a separate secret
> token. That is yours to add.

The alphabet leaves out `I`, `L`, `O` and `U`: a reference gets read down a
telephone and copied off a printed invoice, so a `0` that might be an `O` costs
somebody a support call, and dropping `U` keeps a random string from
occasionally spelling something a customer would rather not read out.

## Order lines

`Order::add()` writes an `OrderItem` per cart line:

```php
$item->quantity   = $cartItem->getQuantity();
$item->price      = $cartItem->getPrice();      // the LINE total, in cents
$item->item_id    = (int) $buyable->getId();
$item->item_class = get_class($buyable);
$item->options    = LineOptions::canonical($cartItem->getOptions());  // or NULL
```

`price` is the line total — quantity times unit price — not the unit price.
`options` is the order's **own copy** of the line's selection, in the same
canonical form the cart line was keyed on; `NULL` when the line had none. Same
reasoning as `freezeCustomer()`: the cart row dies with the checkout, and only
the order's own copy can say what was ordered next year.

Read a line back through the contract — no downcast to the concrete model:

```php
foreach ($order->getItems() as $line) {
    $line->getQuantity();   // int
    $line->getPrice();      // the frozen line total, in cents
    $line->getOptions();    // the frozen selection, or [] — incl. pre-options lines
    $line->getBuyable();    // the LIVE model — see below
}
```

### What a line does *not* freeze

The money and the options are frozen. The title, the description and
everything else are read back **live**:

```php
public function getBuyable(): BuyableInterface
{
    return $this->buyable ??= $this->item_class::load($this->item_id);
}
```

Two consequences that surprise people, and neither is guarded against:

- **Renaming a product rewrites old invoices.** Every past order line shows the
  new name against the old price.
- **Deleting a product breaks its orders.** `load()` on a missing row throws, so
  the line cannot be rendered at all — and neither can the order it is on.

A shop that needs invoices to stay put should soft-delete rather than delete,
and should think twice before letting editors rename sold products. The
selection itself is safe — it is frozen on the line's own `options` column —
but a shop still pricing options through a configuration model (see
[Options](options.md#what-options-are-not-a-price)) has the buyable-load
consequences above on that model too.

## The price convention travels with the order

```php
$order->prices;                 // 'inclusive' | 'exclusive'
$order->getPriceConvention();   // PriceConvention
```

Frozen because "was this total gross or net" is not recoverable from the numbers
alone. A shop that switches convention next year must still be able to reprint
this invoice as it was charged.

## Finding orders

```php
OrderRepository::create()->byOrderId('12-K4M7QX9RTB')->firstOrNull();
OrderRepository::create()->byPaymentId($mollieId)->firstOrNull();
OrderRepository::create()->forCustomer($customer)->all();
OrderRepository::create()->withPaymentStatus(PaymentStatus::Paid)->all();
```

`withPaymentStatus()` takes the enum — the only thing the package ever writes
to the column — and still accepts a plain string for callers that predate it.
One subtlety: a legacy order *reads* as pending through `getPaymentStatus()`,
but its column holds `''`, so filtering on `PaymentStatus::Pending` will not
find it — the query runs against what the column holds; only the reader
interprets.

## Events

| Event | When |
| --- | --- |
| `Created` | The order and its lines are written, before `pay()`. |
| `Paid` | A gateway reports the money arrived. Writes `paid`, redeems the coupon. |
| `PaymentFailed`, `PaymentCanceled`, `PaymentExpired`, `PaymentRefunded` | A gateway says so. Each writes its status onto the order. |

`Created` fires before payment, so a listener on it must not assume the order
was paid for — send the confirmation mail from `Paid`.

## Testing checkout without a database

Two protected seams exist for exactly this: `Cart::newOrder()` and
`Order::newOrderItem()`. Override each with a subclass whose `save()` keeps to
memory and the whole of `checkout()` — the money freezing, the customer copy,
the lines, the events, the payment — runs for real with no connection anywhere
near it. The package's own `tests/Support/InMemoryLineOrder` is the worked
example.

## See also

- [Addresses](addresses.md) — the book, and what freezing copies
- [Customer](customer.md) — the account link, and a full worked checkout
- [Payment](payment.md) — the gateway interface and the events
- [Tax](tax.md) — the convention an order records
