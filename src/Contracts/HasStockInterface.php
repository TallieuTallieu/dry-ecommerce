<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * A buyable there is a finite number of.
 *
 * Opt in and the cart starts consulting stock: {@see CartInterface::canAdd()}
 * asks this buyable's worker whether the quantity the cart would end up holding
 * is available. Leave it out and that call never goes near stock — a buyable
 * with no stock concept is always addable, in any quantity, and needs no worker
 * to say so.
 *
 * `canAdd()` reports; it does not gate. {@see CartInterface::add()} adds either
 * way, because refusing a sale that stock cannot fill today is trade policy and
 * not arithmetic: one shop refuses, another backorders, another oversells and
 * reconciles. A shop reads `canAdd()` and does what it does.
 *
 * That is the whole reason this is a separate interface rather than two more
 * methods on {@see BuyableInterface}. A service, a subscription, a made-to-order
 * meal or a timeslot booking has no count behind it; before this seam existed
 * each of them still had to hand back a stock worker, and the package shipped a
 * `NullStockWorker` so that they could. Absence of stock is now expressed by
 * absence, which is the only way to express it that cannot be misread.
 *
 * A buyable is free to return a worker over any stock it likes — a shop can keep
 * several ({@see \Tnt\Ecommerce\Model\Stock}), and which one applies is the
 * buyable's decision, not the cart's.
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
