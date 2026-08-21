<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Something that adds up to a total — a cart, or the order it became.
 *
 * All three amounts are integer cents.
 *
 * @see \Tnt\Ecommerce\Money
 */
interface TotalingInterface
{
    /**
     * @return int
     */
    public function getSubTotal(): int;

    /**
     * @return int
     */
    public function getTotal(): int;

    /**
     * @return int
     */
    public function getReduction(): int;
}
