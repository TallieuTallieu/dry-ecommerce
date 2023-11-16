<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Interface StockWorkerInterface
 * @package app\ecommerce\contracts
 */
interface StockWorkerInterface
{
    /**
     * @param BuyableInterface $buyable
     * @param float $quantity
     * @return bool
     */
    public function isAvailable(BuyableInterface $buyable, float $quantity = 1): bool;

    /**
     * @param BuyableInterface $buyable
     * @param float $quantity
     * @return mixed
     */
    public function increment(BuyableInterface $buyable, float $quantity = 1);

    /**
     * @param BuyableInterface $buyable
     * @param float $quantity
     * @return mixed
     */
    public function decrement(BuyableInterface $buyable, float $quantity = 1);

    /**
     * @param BuyableInterface $buyable
     * @return int
     */
    public function getQuantity(BuyableInterface $buyable): int;
}