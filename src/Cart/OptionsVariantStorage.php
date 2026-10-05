<?php

namespace Tnt\Ecommerce\Cart;

use Tnt\Ecommerce\Contracts\OrderItemInterface;
use Tnt\Ecommerce\Contracts\VariantInterface;
use Tnt\Ecommerce\Contracts\VariantStorageInterface;
use Tnt\Ecommerce\Model\OrderItem;

/**
 * The frozen variant under the order line's {@see Variants::KEY} option —
 * `{"id", "title", "price"}` where the cart line had `{"id"}`. Needs no
 * schema of its own: the options column already holds it. See
 * docs/variants.md.
 */
final class OptionsVariantStorage implements VariantStorageInterface
{
    public function freeze(
        OrderItem $line,
        VariantInterface $variant,
        int $unitPrice
    ): void {
        $options = $line->getOptions();
        $options[Variants::KEY] = [
            'id' => $variant->getId(),
            'title' => $variant->getTitle(),
            'price' => $unitPrice,
        ];

        $line->options = LineOptions::canonical($options);
        $line->save();
    }

    public function frozenOf(OrderItemInterface $line): ?VariantInterface
    {
        $options = $line->getOptions();
        $entry = Variants::entry($options);
        $id = Variants::idIn($options);
        $title = $entry['title'] ?? null;
        $price = $entry['price'] ?? null;

        if ($id === null || !is_string($title)) {
            return null;
        }

        return new Variant($id, $title, is_int($price) ? $price : null);
    }
}
