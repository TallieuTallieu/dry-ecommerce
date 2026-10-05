<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * A buyable whose unit price depends on the options its line was added with —
 * a size, a variant. Opt in and a cart line prices itself through
 * {@see getPriceFor()} instead of {@see BuyableInterface::getPrice()}; leave
 * it out and options never touch the price.
 *
 * The package asks the buyable to price the options, not whether they are
 * allowed: validate the selection before {@see \Tnt\Ecommerce\Cart\Cart::add()}.
 * See docs/options.md.
 */
interface PricedByOptionsInterface extends BuyableInterface
{
    /**
     * The unit price, in cents, of a line carrying these options. Options it
     * does not price — none at all, an unknown variant — fall back to
     * {@see BuyableInterface::getPrice()}, so a line is never without a price.
     *
     * @param array<array-key, mixed> $options The line's options, canonical
     * @return int
     */
    public function getPriceFor(array $options): int;
}
