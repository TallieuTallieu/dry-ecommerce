<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Counts how many of a buyable there are, reached through
 * {@see HasStockInterface::getStockWorker()}. Quantities are whole — a shop
 * selling by weight counts in grams, as its money is in cents. See
 * docs/stock.md.
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
     * Take stock out. An implementation may refuse to go below zero —
     * {@see \Tnt\Ecommerce\Stock\StockWorker} refuses by default and raises
     * {@see \Tnt\Ecommerce\Stock\StockWouldGoNegative}.
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
