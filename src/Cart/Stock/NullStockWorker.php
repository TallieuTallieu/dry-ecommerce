<?php

namespace Tnt\Ecommerce\Stock;

use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\StockWorkerInterface;

class NullStockWorker implements StockWorkerInterface
{
    public function isAvailable(BuyableInterface $buyable, float $quantity = 1): bool
    {
        return true;
    }

    public function increment(BuyableInterface $buyable, float $quantity = 1)
    {
        // Do nothing
    }

    public function decrement(BuyableInterface $buyable, float $quantity = 1)
    {
        // Do nothing
    }

    public function getQuantity(BuyableInterface $buyable): int
    {
        return 0;
    }
}