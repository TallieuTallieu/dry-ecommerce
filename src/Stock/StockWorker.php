<?php

namespace Tnt\Ecommerce\Stock;

use Oak\Dispatcher\Facade\Dispatcher;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\StockWorkerInterface;
use Tnt\Ecommerce\Events\Stock\Decremented;
use Tnt\Ecommerce\Events\Stock\Incremented;
use Tnt\Ecommerce\Model\Stock;
use Tnt\Ecommerce\Model\StockItem;
use Tnt\Ecommerce\Repository\StockItemRepository;
use Tnt\Ecommerce\Repository\StockRepository;

/**
 * Counts one named stock, looked up by `hid` on first use. By default a
 * decrement below zero raises {@see StockWouldGoNegative}; built with
 * `allowNegative: true` the count goes under instead. See docs/stock.md.
 */
class StockWorker implements StockWorkerInterface
{
    private ?Stock $stock = null;

    /**
     * @param string $stockHid The `hid` of the stock row to count.
     * @param bool $allowNegative Whether this stock may hold less than none —
     *                            true for a shop that backorders.
     */
    public function __construct(
        private readonly string $stockHid,
        private readonly bool $allowNegative = false
    ) {}

    /**
     * The stock this worker counts, or null when no stock carries its hid.
     *
     * @return Stock|null
     */
    private function stock(): ?Stock
    {
        return $this->stock ??= StockRepository::create()
            ->byHid($this->stockHid)
            ->firstOrNull();
    }

    /**
     * The line holding a buyable in this stock, or null when there is none —
     * which is what "never stocked" looks like, and is not an error.
     *
     * @param BuyableInterface $buyable
     * @return StockItem|null
     */
    private function getStockItem(BuyableInterface $buyable): ?StockItem
    {
        $stock = $this->stock();

        if ($stock === null) {
            return null;
        }

        return StockItemRepository::create()
            ->forBuyable($stock, $buyable)
            ->firstOrNull();
    }

    /**
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return bool
     */
    public function isAvailable(
        BuyableInterface $buyable,
        int $quantity = 1
    ): bool {
        $stockItem = $this->getStockItem($buyable);

        return $stockItem !== null && $stockItem->quantity >= $quantity;
    }

    /**
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return void
     */
    public function increment(
        BuyableInterface $buyable,
        int $quantity = 1
    ): void {
        $stock = $this->stock();

        if ($stock === null) {
            return;
        }

        $stockItem = $this->getStockItem($buyable);

        if ($stockItem !== null) {
            $stockItem->updated = time();
            $stockItem->quantity = $stockItem->quantity + $quantity;
            $stockItem->save();
        } else {
            $stockItem = new StockItem();
            $stockItem->created = time();
            $stockItem->updated = time();
            $stockItem->item_id = (int) $buyable->getId();
            $stockItem->item_class = get_class($buyable);
            $stockItem->stock = $stock;
            $stockItem->quantity = $quantity;
            $stockItem->save();
        }

        Dispatcher::dispatch(
            Incremented::class,
            new Incremented($this, $buyable, $quantity)
        );
    }

    /**
     * Take stock out. Going below zero refuses (nothing written) or goes
     * under, per the worker's `allowNegative`; a never-stocked buyable is a
     * silent no-op.
     *
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return void
     *
     * @throws StockWouldGoNegative If the count would go below zero and this
     *                              stock does not allow that.
     */
    public function decrement(
        BuyableInterface $buyable,
        int $quantity = 1
    ): void {
        $stockItem = $this->getStockItem($buyable);

        if ($stockItem === null) {
            return;
        }

        $remaining = $stockItem->quantity - $quantity;

        if ($remaining < 0 && !$this->allowNegative) {
            throw new StockWouldGoNegative(
                $buyable,
                $stockItem->quantity,
                $quantity
            );
        }

        $stockItem->updated = time();
        $stockItem->quantity = $remaining;
        $stockItem->save();

        Dispatcher::dispatch(
            Decremented::class,
            new Decremented($this, $buyable, $quantity)
        );
    }

    /**
     * @param BuyableInterface $buyable
     * @return int
     */
    public function getQuantity(BuyableInterface $buyable): int
    {
        $stockItem = $this->getStockItem($buyable);

        if ($stockItem === null) {
            return 0;
        }

        return $stockItem->quantity;
    }
}
