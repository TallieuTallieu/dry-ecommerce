<?php

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Model\OrderItem;

/**
 * Where an order line keeps the variant it was sold as, chosen with
 * `ecommerce.variant_storage`: in the line's options
 * ({@see \Tnt\Ecommerce\Cart\OptionsVariantStorage}, the default), in columns,
 * or in a table of the shop's own.
 *
 * Only the order line's copy is the storage's. A cart line holds a reference —
 * the variant's id in its options, which is what makes two variants two lines
 * — and reads the title and price live off the buyable; see
 * {@see \Tnt\Ecommerce\Cart\Variants}. See docs/variants.md.
 */
interface VariantStorageInterface
{
    /**
     * Keep the variant an order line was sold as. Called once per line at
     * checkout, after the line is saved — it has its id — and before the
     * cart's next line is.
     *
     * The concrete row, not {@see OrderItemInterface}: a storage may write
     * onto the line itself and save it, as the options default does.
     *
     * @param OrderItem $line
     * @param VariantInterface $variant As the buyable offered it at checkout
     * @param int $unitPrice What one was charged, in cents
     * @return void
     */
    public function freeze(
        OrderItem $line,
        VariantInterface $variant,
        int $unitPrice
    ): void;

    /**
     * The copy {@see freeze()} kept for this line, or null — a line without
     * a variant, or one placed before the shop sold variants.
     *
     * @param OrderItemInterface $line
     * @return VariantInterface|null
     */
    public function frozenOf(OrderItemInterface $line): ?VariantInterface;
}
