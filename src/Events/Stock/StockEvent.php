<?php

namespace Tnt\Ecommerce\Events\Stock;

use Oak\Dispatcher\Event;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\StockWorkerInterface;

/**
 * Something happened to a stock line.
 *
 * The quantity is the amount that moved, not the amount left — a listener that
 * wants the new count asks the worker for it.
 */
abstract class StockEvent extends Event
{
    private StockWorkerInterface $stockWorker;

    private BuyableInterface $buyable;

    /**
     * How many moved.
     */
    private int $quantity;

    /**
     * @param StockWorkerInterface $stockWorker
     * @param BuyableInterface $buyable
     * @param int $quantity
     */
    public function __construct(
        StockWorkerInterface $stockWorker,
        BuyableInterface $buyable,
        int $quantity
    ) {
        $this->stockWorker = $stockWorker;
        $this->buyable = $buyable;
        $this->quantity = $quantity;
    }

    /**
     * @return StockWorkerInterface
     */
    public function getStockWorker(): StockWorkerInterface
    {
        return $this->stockWorker;
    }

    /**
     * @return BuyableInterface
     */
    public function getBuyable(): BuyableInterface
    {
        return $this->buyable;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
