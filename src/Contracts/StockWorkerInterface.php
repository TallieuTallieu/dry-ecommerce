<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Counts how many of a buyable there are.
 *
 * Reached through {@see HasStockInterface::getStockWorker()}, which is what
 * makes it optional: a buyable that is not counted never produces one of these,
 * and nothing in the cart asks for one.
 *
 * # Quantities are whole
 *
 * Every quantity below is an `int`, matching {@see CartItemInterface} and
 * `ecommerce_stock_item.quantity`, which has been an `int(11)` column since it
 * was created. The `float` these methods used to take was never honoured
 * anywhere: it went into an integer column, came back rounded, and
 * `getQuantity()` cast it back to `int` on the way out. Half a thing was
 * expressible in three signatures and storable in none of them.
 *
 * A shop that genuinely sells by weight — 0.75 kg of cheese — is not served by
 * putting the fraction back here either. Its unit is the gram, and its price is
 * per gram, exactly as its money is in cents rather than in euro.
 */
interface StockWorkerInterface
{
    /**
     * Whether this buyable can be had in this quantity.
     *
     * A buyable that has never been stocked is not available, rather than
     * unlimited: a stock that has no line for something does not know of it.
     *
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return bool
     */
    public function isAvailable(
        BuyableInterface $buyable,
        int $quantity = 1
    ): bool;

    /**
     * Put stock in, creating the line if this buyable has never been stocked.
     *
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return void
     */
    public function increment(
        BuyableInterface $buyable,
        int $quantity = 1
    ): void;

    /**
     * Take stock out.
     *
     * An implementation may refuse to take out more than it holds rather than
     * go below zero, and whether it does is the shop's to configure:
     * {@see \Tnt\Ecommerce\Stock\StockWorker} refuses by default and raises
     * {@see \Tnt\Ecommerce\Stock\StockWouldGoNegative}. A caller that has not
     * checked {@see isAvailable()} first should expect either answer.
     *
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return void
     */
    public function decrement(
        BuyableInterface $buyable,
        int $quantity = 1
    ): void;

    /**
     * How many there are, or 0 when this buyable is not stocked here.
     *
     * @param BuyableInterface $buyable
     * @return int
     */
    public function getQuantity(BuyableInterface $buyable): int;
}
