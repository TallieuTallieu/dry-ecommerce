# Variants

A buyable sold as one of several versions — a croissant in 35 flavours, a gift
basket of € 40 to € 75. The shop says what its variants are; the package keeps
every line on one of them, prices the line at it, and freezes it onto the order.

## Opting in

Two contracts. The buyable implements `HasVariantsInterface`, each variant
`VariantInterface` — a model with its own table, a list on the buyable, an enum,
whatever the shop keeps them as:

```php
use Tnt\Ecommerce\Contracts\HasVariantsInterface;
use Tnt\Ecommerce\Contracts\VariantInterface;

class Product extends Model implements HasVariantsInterface
{
    public function getVariants(): iterable
    {
        return $this->visibleVariations(); // in the order the shop lists them
    }

    public function getVariant(string $id): ?VariantInterface
    {
        foreach ($this->getVariants() as $variant) {
            if ($variant->getId() === $id) {
                return $variant;
            }
        }

        return null;
    }
}

class ProductVariation extends Model implements VariantInterface
{
    public function getId(): string
    {
        return (string) $this->id;
    }
    public function getTitle(): string
    {
        return $this->name;
    }
    public function getPrice(): ?int
    {
        return $this->price_cents ?: null;
    }
}
```

`getPrice()` is cents, like everywhere ([Money](money.md)); null sells at the
buyable's own `getPrice()`.

`getVariant()` is what the cart validates against and what a line reads its
variant back through. Let it answer only what `getVariants()` offers and a
withdrawn variant's lines fall back to the buyable's price; let it answer
withdrawn ones too and those lines keep their variant — then check a posted id
against `getVariants()` yourself, since the cart will accept it.

## Adding a line

The variant is `add()`'s fifth argument:

```php
$variant = $product->getVariant($request->post->string('variant'));

$cart->add($product, 2, variant: $variant);
```

- **Null takes the first** of `getVariants()`. Turn a posted id the shop does
  not offer into null and a stale or tampered form lands on the first rather
  than on nothing — a buyable with variants is never sold as none of them.
- **A variant the buyable does not offer throws** `UnknownVariant`: another
  buyable's, or any variant on a buyable without them. That is a programming
  error, not a visitor's.
- **Two variants are two lines**, the same variant merges — the variant is part
  of the line's identity, beside its [options](options.md) and its parent.

## What a line says

|                  | Cart line (`CartItemInterface`)                     | Order line (`OrderItemInterface`)         |
| ---------------- | --------------------------------------------------- | ----------------------------------------- |
| `getVariant()`   | The buyable's variant **now**, via `getVariant()`   | The frozen copy: id, title, price as sold |
| `getVariantId()` | The id the line was added as, withdrawn or not      | —                                         |
| `getPrice()`     | `quantity × (variant price ?? buyable price)`, live | The line total frozen at checkout         |
| `getOptions()`   | The shop's own options                              | The shop's own options                    |

A cart line holds a reference, so a renamed or repriced variant shows its new
title and price until checkout — exactly as `getPrice()` already does for the
buyable. A line whose variant `getVariant()` no longer answers for reads null
and prices at the buyable until the shop removes it. Checked out like that,
the order line is charged the buyable's price and keeps the variant's id with
an empty title, so the order still says which one was picked.

## Where the variant is stored

In columns of its own, beside the [options](options.md) rather than inside
their JSON. The migration is `AddVariantToLineTables`.

| Table                  | Column          | Holds                                                  |
| ---------------------- | --------------- | ------------------------------------------------------ |
| `ecommerce_cart_item`  | `variant`       | The variant's id, or NULL. Part of the merge key.      |
| `ecommerce_order_item` | `variant`       | The same id, copied at checkout.                       |
| `ecommerce_order_item` | `variant_title` | The title it was sold under, or NULL when it was gone. |

The unit price needs no column: the order line's `price` is the frozen line
total, `unit price × quantity`, so `getVariant()->getPrice()` is
`price / quantity`, exactly.

`variant` is `utf8mb4_bin`, like `options`: the cart compares it with `=`, and
under the default case- and accent-insensitive collation variant `m` would
merge into variant `M`.

Options stay the shop's alone: nothing is added to them, and nothing in them —
a tampered form included — picks the variant. Reporting on what sold is a plain
query:

```sql
SELECT variant, variant_title, SUM(quantity)
FROM ecommerce_order_item
WHERE item_class = 'Product' AND item_id = 12
GROUP BY variant, variant_title;
```

## See also

- [Options](options.md) — the per-line choices a variant sits beside.
- [Buyable](buyable.md) — the other capabilities a buyable opts into.
- [Orders](orders.md) — what else an order line freezes.
