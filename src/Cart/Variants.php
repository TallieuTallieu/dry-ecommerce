<?php

namespace Tnt\Ecommerce\Cart;

use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\HasVariantsInterface;
use Tnt\Ecommerce\Contracts\VariantInterface;
use Tnt\Ecommerce\UnknownVariant;

/**
 * The one place a line's variant is resolved, looked up and priced, so the
 * row-backed and the in-memory cart line cannot drift apart. The line itself
 * keeps only the variant's id, in a `variant` column of its own. See
 * docs/variants.md.
 */
final class Variants
{
    private function __construct() {}

    /**
     * The variant a line of this buyable is added as: the one given, checked
     * against the buyable, or its first when given none. Null for a buyable
     * without variants.
     *
     * @param BuyableInterface $buyable
     * @param VariantInterface|null $variant
     * @return VariantInterface|null
     *
     * @throws UnknownVariant When the buyable does not offer the variant.
     */
    public static function resolve(
        BuyableInterface $buyable,
        ?VariantInterface $variant
    ): ?VariantInterface {
        if (!($buyable instanceof HasVariantsInterface)) {
            if ($variant !== null) {
                throw UnknownVariant::on($buyable, $variant);
            }

            return null;
        }

        if ($variant === null) {
            foreach ($buyable->getVariants() as $first) {
                return $first;
            }

            return null;
        }

        return $buyable->getVariant($variant->getId()) ??
            throw UnknownVariant::on($buyable, $variant);
    }

    /**
     * The variant a cart line's id points at, as the buyable offers it now;
     * null when there is none, or it is no longer offered.
     *
     * @param BuyableInterface $buyable
     * @param string|null $id
     * @return VariantInterface|null
     */
    public static function of(
        BuyableInterface $buyable,
        ?string $id
    ): ?VariantInterface {
        return $id !== null && $buyable instanceof HasVariantsInterface
            ? $buyable->getVariant($id)
            : null;
    }

    /**
     * The unit price, in cents, of this buyable sold as this variant: the
     * variant's own price, or the buyable's.
     *
     * @param BuyableInterface $buyable
     * @param VariantInterface|null $variant
     * @return int
     */
    public static function unitPrice(
        BuyableInterface $buyable,
        ?VariantInterface $variant
    ): int {
        return $variant?->getPrice() ?? $buyable->getPrice();
    }
}
