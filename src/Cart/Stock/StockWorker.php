<?php

namespace Tnt\Ecommerce\Stock;

use dry\db\FetchException;
use Oak\Dispatcher\Facade\Dispatcher;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\StockWorkerInterface;
use Tnt\Ecommerce\Events\Stock\Decremented;
use Tnt\Ecommerce\Events\Stock\Incremented;
use Tnt\Ecommerce\Model\Stock;
use Tnt\Ecommerce\Model\StockItem;

class StockWorker implements StockWorkerInterface
{
    /**
     * @var string $stockHid
     */
    private $stockHid;

    /**
     * @var Stock $stock
     */
    private $stock;

    /**
     * StockWorker constructor.
     * @param string $stockHid
     */
    public function __construct(string $stockHid)
    {
        $this->stockHid = $stockHid;
        $this->stock = Stock::load_by('hid', $this->stockHid);
    }

    /**
     * @param BuyableInterface $buyable
     * @return StockItem
     */
    private function getStockItem(BuyableInterface $buyable): StockItem
    {
        return StockItem::one('
            WHERE stock = ?
            AND item_class = ?
            AND item_id = ?
        ', $this->stock->id, get_class($buyable), $buyable->getId());
    }

    /**
     * @param BuyableInterface $buyable
     * @param float $quantity
     * @return bool
     */
    public function isAvailable(BuyableInterface $buyable, float $quantity = 1): bool
    {
        try {

            $stockItem = $this->getStockItem($buyable);
            return ($stockItem->quantity >= $quantity);

        } catch (FetchException $e) {
            //
        }

        return false;
    }

    /**
     * @param BuyableInterface $buyable
     * @param float $quantity
     * @return mixed|void
     */
    public function increment(BuyableInterface $buyable, float $quantity = 1)
    {
        try {

            $stockItem = $this->getStockItem($buyable);
            $stockItem->updated = time();
            $stockItem->quantity = $stockItem->quantity + $quantity;
            $stockItem->save();

        } catch (FetchException $e) {

            $stockItem = new StockItem();
            $stockItem->created = time();
            $stockItem->updated = time();
            $stockItem->item_id = $buyable->getId();
            $stockItem->item_class = get_class($buyable);
            $stockItem->stock = $this->stock;
            $stockItem->quantity = $quantity;
            $stockItem->save();
        }

        Dispatcher::dispatch(Incremented::class, new Incremented($this, $buyable, $quantity));
    }

    /**
     * @param BuyableInterface $buyable
     * @param float $quantity
     * @return mixed|void
     */
    public function decrement(BuyableInterface $buyable, float $quantity = 1)
    {
        try {

            $stockItem = $this->getStockItem($buyable);
            $stockItem->updated = time();
            $stockItem->quantity = $stockItem->quantity - $quantity; // @TODO call isAvailable()
            $stockItem->save();

            Dispatcher::dispatch(Decremented::class, new Decremented($this, $buyable, $quantity));

        } catch (FetchException $e) {
            //
        }
    }

    /**
     * @param BuyableInterface $buyable
     * @return int
     */
    public function getQuantity(BuyableInterface $buyable): int
    {
        try {

            $stockItem = $this->getStockItem($buyable);
            return $stockItem->quantity;

        } catch (FetchException $e) {
            //
        }

        return 0;
    }
}