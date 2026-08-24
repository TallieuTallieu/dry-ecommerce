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
 * The shipped {@see StockWorkerInterface}, and the reason stock became an opt-in
 * capability rather than being dropped: a shop that counts what it sells still
 * gets this, unchanged in behaviour, by returning one from
 * {@see \Tnt\Ecommerce\Contracts\HasStockInterface::getStockWorker()}.
 *
 * The stock row is looked up on first use rather than in the constructor, so
 * constructing a worker no longer requires a database. Which stock a buyable is
 * counted in is the buyable's decision — it names one by `hid` here — and no
 * longer something the container guesses at: the old
 * `StockWorkerInterface => StockWorker` binding could not have been resolved,
 * because there is no stock to count without knowing which one.
 *
 * # Whether the count may go below zero
 *
 * The same decision, made the same way. A stock built plainly refuses a
 * decrement that would take it negative and raises
 * {@see StockWouldGoNegative}; one built with `allowNegative: true` lets the
 * count go under, where a negative reads as how many the shop owes.
 *
 * ```php
 * new StockWorker('warehouse');                       // refuses
 * new StockWorker('warehouse', allowNegative: true);  // backorders
 * ```
 *
 * Refusing is the default because a shop that has not thought about it is
 * better served by hearing that it oversold than by a negative number nobody
 * looks at. Neither answer is clamped to zero: a count that silently disagrees
 * with what was taken out is the one outcome that helps nobody.
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
     * Take stock out.
     *
     * Taking out more than there is does one of two things, depending on how
     * this worker was built. A stock that allows negatives goes under and the
     * count is how many the shop owes; one that does not raises
     * {@see StockWouldGoNegative} and nothing is written. See the note on the
     * class for why those are the two answers and not three.
     *
     * A buyable this stock has never held is not stocked here at all, so there
     * is no line to take anything out of and nothing happens — which is the
     * same silence {@see getQuantity()} answers with, not a refusal.
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
