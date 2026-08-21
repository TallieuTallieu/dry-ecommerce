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
 * Counts one named stock.
 *
 * The stock row is looked up on first use rather than in the constructor, so
 * constructing a worker — which the container does for anything asking for
 * {@see StockWorkerInterface} — no longer requires a database.
 */
class StockWorker implements StockWorkerInterface
{
    private string $stockHid;

    private ?Stock $stock = null;

    /**
     * @param string $stockHid
     */
    public function __construct(string $stockHid)
    {
        $this->stockHid = $stockHid;
    }

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
     * @param float $quantity
     * @return bool
     */
    public function isAvailable(
        BuyableInterface $buyable,
        float $quantity = 1
    ): bool {
        $stockItem = $this->getStockItem($buyable);

        return $stockItem !== null && $stockItem->quantity >= $quantity;
    }

    /**
     * @param BuyableInterface $buyable
     * @param float $quantity
     * @return mixed|void
     */
    public function increment(BuyableInterface $buyable, float $quantity = 1)
    {
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
     * @param BuyableInterface $buyable
     * @param float $quantity
     * @return mixed|void
     */
    public function decrement(BuyableInterface $buyable, float $quantity = 1)
    {
        $stockItem = $this->getStockItem($buyable);

        if ($stockItem === null) {
            return;
        }

        $stockItem->updated = time();
        $stockItem->quantity = $stockItem->quantity - $quantity; // @TODO call isAvailable()
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

        return (int) $stockItem->quantity;
    }
}
