<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\HasStockInterface;
use Tnt\Ecommerce\Contracts\StockWorkerInterface;

/**
 * A buyable that is counted and carries no tax.
 *
 * One of the four capability combinations, and the counterpart to
 * {@see FakeTaxableBuyable}. The worker is injected rather than built here
 * because the shipped {@see \Tnt\Ecommerce\Stock\StockWorker} reads a database;
 * {@see FakeStockWorker} is the in-memory one these tests count with.
 */
class FakeStockedBuyable extends FakeBuyable implements HasStockInterface
{
    /**
     * @param string $id
     * @param int $price The unit price, in cents.
     * @param StockWorkerInterface $stockWorker
     * @param string $title
     */
    public function __construct(
        string $id,
        int $price,
        private readonly StockWorkerInterface $stockWorker,
        string $title = 'A counted thing'
    ) {
        parent::__construct($id, $price, $title);
    }

    public function getStockWorker(): StockWorkerInterface
    {
        return $this->stockWorker;
    }
}
