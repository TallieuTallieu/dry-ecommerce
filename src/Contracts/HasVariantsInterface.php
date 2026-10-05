<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * A buyable sold as one of several variants. Opt in and every line of it
 * carries a variant: {@see CartInterface::add()} checks the one it is given
 * belongs here and takes the first when it is given none, the line prices
 * at the variant's price, and checkout freezes the variant onto the order
 * line. See docs/variants.md.
 */
interface HasVariantsInterface extends BuyableInterface
{
    /**
     * The variants on offer, in the order a shop lists them. The first is
     * what a line without a pick is added as.
     *
     * @return iterable<VariantInterface>
     */
    public function getVariants(): iterable;

    /**
     * One variant by id, or null when this buyable has none by that id.
     * Answering for a variant no longer on offer keeps a line that already
     * holds it priced and titled; a shop that does checks a posted id
     * against {@see getVariants()} itself.
     *
     * @param string $id
     * @return VariantInterface|null
     */
    public function getVariant(string $id): ?VariantInterface;
}
