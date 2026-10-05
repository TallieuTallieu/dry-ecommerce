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

|                | Cart line (`CartItemInterface`)                     | Order line (`OrderItemInterface`)         |
| -------------- | --------------------------------------------------- | ----------------------------------------- |
| `getVariant()` | The buyable's variant **now**, via `getVariant()`   | The frozen copy: id, title, price as sold |
| `getPrice()`   | `quantity × (variant price ?? buyable price)`, live | The line total frozen at checkout         |
| `getOptions()` | The shop's own options                              | The shop's own options                    |

A cart line holds a reference, so a renamed or repriced variant shows its new
title and price until checkout — exactly as `getPrice()` already does for the
buyable. A line whose variant `getVariant()` no longer answers for reads null
and prices at the buyable until the shop removes it. Checked out like that,
the order line is charged the buyable's price and freezes the variant by its
id alone — empty title, that price — so the order still says which one was
picked.

## Where the variant is stored

Two halves, and only the second is configurable.

**The reference is always in the options.** A cart line carries the variant's
id under the reserved `_variant` option — `{"id": "40"}` — whatever the
storage. The cart merges on options, so this is what makes two variants two
lines, with nothing for the cart storages to learn. Whatever a shop puts under
that key itself is replaced, so a posted form cannot pick its variant through
the options. The order line copies the options, reference included.

**The frozen copy is the storage's.** `ecommerce.variant_storage` names a
`VariantStorageInterface` with two methods:

| Method                                   | Called                                                       |
| ---------------------------------------- | ------------------------------------------------------------ |
| `freeze(OrderItem, Variant, $unitPrice)` | Once per line at checkout, **after** the order line is saved |
| `frozenOf(OrderItemInterface)`           | By the order line's `getVariant()` — the copy, or null       |

The default, `OptionsVariantStorage`, needs no schema: it writes the copy
over the reference in the order line's options — `{"id", "title", "price"}` —
and saves the line again.

A shop that wants the sold variants in columns or a table — to report on them,
to join them, to keep more than a title and a price — writes its own storage
and names it in config. `freeze()` runs after the save, so the line has an id
to key a row on:

```php
final class OrderVariationStorage implements VariantStorageInterface
{
    public function freeze(
        OrderItem $line,
        VariantInterface $variant,
        int $unitPrice
    ): void {
        $row = new OrderVariation();
        $row->order_item = $line->id;
        $row->variation = $variant->getId();
        $row->title = $variant->getTitle();
        $row->price_cents = $unitPrice;
        $row->save();
    }

    public function frozenOf(OrderItemInterface $line): ?VariantInterface
    {
        $row = OrderVariation::forLine($line);

        return $row
            ? new Variant($row->variation, $row->title, $row->price_cents)
            : null;
    }
}
```

```php
// config/ecommerce.php
'variant_storage' => OrderVariationStorage::class,
```

Nothing else changes: the cart, `add()`, the price and both lines'
`getVariant()` read the same either way. A storage swapped on a live shop reads
only what it froze itself — lines placed before the switch answer null unless
it falls back to the old storage.

## See also

- [Options](options.md) — the per-line payload the default storage rides on.
- [Buyable](buyable.md) — the other capabilities a buyable opts into.
- [Orders](orders.md) — what else an order line freezes.
