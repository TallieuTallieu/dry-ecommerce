# Fulfillment

How an order gets to the customer, what that costs, and the information the
method needs collecting before it can be used.

A fulfillment method is **registered by the shop, not stored by the package**.
`ecommerce_fulfillment_method` exists, but the methods a cart can choose between
come from `ShopInterface`, in memory, per request — so a method can be a
database row, a hard-coded class, or something worked out from the cart.

## Registering methods

```php
$shop = $app->get(\Tnt\Ecommerce\Contracts\ShopInterface::class);

$shop->addFulfillment(new Pickup());
$shop->addFulfillment(new HomeDelivery());

$shop->getFulfillments();    // everything registered
$shop->hasFulfillment('pickup');
$shop->getFulfillment('pickup');
```

Do this while booting, in a service provider — a cart resolved before the
methods are registered cannot choose one, and `Cart::setFulfillment()` silently
ignores a method the shop does not know:

```php
public function setFulfillment(FulfillmentInterface $fulfillment)
{
    if (!$this->shop->hasFulfillment($fulfillment->getId())) {
        return;    // no error, no exception, nothing chosen
    }

    $this->storage->setFulfillmentId($fulfillment->getId());
}
```

## The contract

```php
interface FulfillmentInterface
{
    public function getId();                            // string|int
    public function getTitle(): string;
    public function getCost(CartInterface $cart): int;  // CENTS

    public function getAttribute(string $name);
    public function attributeOr(string $name, mixed $default): mixed;
    public function setAttribute(string $name, $value);
    public function hasAttribute(string $name): bool;
    public function requireAttributes(): array;
    public function validateAttributes(): bool;
}
```

### The cost is a function of the cart

`getCost()` is handed the cart, so free delivery over a threshold, per-item
handling and weight bands are all ordinary implementations rather than special
cases:

```php
public function getCost(CartInterface $cart): int
{
    return $cart->getSubTotal() >= 5000 ? 0 : 495;
}
```

Be careful what you read off the cart inside it. `Cart::getTotal()` calls
`getFulfillmentCost()`, which calls this — a `getCost()` that reads
`getTotal()` recurses until PHP gives up. Read `getSubTotal()` instead.

### Attributes are the method's own questions

A pickup point needs to know which point; a courier needs a delivery window.
Those are questions one method asks and the others do not, so they are
attributes on the method rather than columns on the order:

```php
$method->setAttribute('pickup_point', 'GENT-CENTRUM');
$method->requireAttributes();       // ['pickup_point']
$method->validateAttributes();      // false until every required one is set
```

`HasFulfillmentAttributes` implements the whole of that against an
`AttributeStorageInterface`, so a method usually just uses the trait and
declares what it requires:

```php
use Tnt\Ecommerce\Fulfillment\HasFulfillmentAttributes;

class Pickup implements FulfillmentInterface
{
    use HasFulfillmentAttributes;

    public function requireAttributes(): array
    {
        return ['pickup_point'];
    }
}
```

### Required and optional attributes read differently

`getAttribute()` distinguishes the two:

- a **required** attribute that was never set throws `MissingAttribute` — a
  delivery window that is silently absent is worse than one that stops the
  checkout;
- any other unset attribute answers `null`.

So `requireAttributes()` is not documentation, it changes behaviour.

The throw is right at freeze time and wrong for a mere peek — a form
prefilling "the chosen point, or a placeholder" must not blow up on the very
page that exists to get the attribute set. `attributeOr()` is the peek:

```php
$method->attributeOr('pickup_point', null); // set → the value; unset → null
$method->attributeOr('timeslot', 'morning'); // any default you like
```

Never a throw, required or not. It is a separate method rather than a default
parameter on `getAttribute()` because an explicit `null` default would be
indistinguishable from no argument there — and `getAttribute()`'s guarded
read behaves exactly as it always did.

### Give the method the shop's storage

The trait falls back to a **fresh `InMemoryAttributeStorage`** when nothing has
been injected, which is per-instance and does not survive the request. The
provider binds `AttributeStorageInterface` to `SessionAttributeStorage` as a
singleton, but the trait only uses it if it is handed over:

```php
$method->setAttributeStorage($app->get(AttributeStorageInterface::class));
```

Miss that and a pickup point chosen on one page is gone on the next, with no
error — the attribute simply reads back unset.

## Tax on delivery

Delivery is taxed at the shop's own rate, `ecommerce.delivery_tax_rate`, not at
the rate of the goods in the cart. It defaults to `0`, which means no tax figure
appears for delivery at all.

That is a simplification, and a deliberate one: strictly, delivery follows the
rate of what is being delivered, which is not a single rate for a mixed cart.
See [Tax](tax.md#delivery).

## What the order keeps

`ecommerce_order.fulfillment_method` stores the **id**, as a string. Not the
title, not the cost breakdown — the cost is already frozen in
`fulfillment_cost`.

The **required** attributes are frozen too: checkout copies them as a JSON
object onto `ecommerce_order.fulfillment_attributes`, because they live in the
session until that moment and the session does not outlive the checkout. Read
them back with `Order::getFulfillmentAttribute()` — never through the method,
whose storage belongs to whoever is filling a cart today. See
[Orders](orders.md#the-frozen-fulfillment-attributes).

So an order whose method has since been unregistered has an id that resolves to
nothing, and `Order::getFulfillment()` will not find it. If a shop retires a
delivery method, keep it registered for as long as it needs to display old
orders.

## See also

- [Cart](cart.md) — `setFulfillment()`, `getFulfillmentCost()`
- [Tax](tax.md) — how delivery is taxed
- [Orders](orders.md) — what an order freezes
