<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * A buyable there is a finite number of. Opt in and
 * {@see CartInterface::canAdd()} consults this buyable's stock; leave it out
 * and the buyable is always addable. See docs/stock.md.
 *
 * @see StockWorkerInterface
 * @see \Tnt\Ecommerce\Stock\StockWorker
 */
interface HasStockInterface extends BuyableInterface
{
    /**
     * The stock this buyable is counted in.
     *
     * @return StockWorkerInterface
     */
    public function getStockWorker(): StockWorkerInterface;
}
