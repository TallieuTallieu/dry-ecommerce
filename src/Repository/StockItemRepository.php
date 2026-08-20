<?php

namespace Tnt\Ecommerce\Repository;

use Tnt\Dbi\Criteria\Equals;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Model\Stock;
use Tnt\Ecommerce\Model\StockItem;

/**
 * Reads `ecommerce_stock_item`.
 *
 * Like a cart line, a stock line points at a buyable by class name and foreign
 * id rather than by a real foreign key, so the lookup that matters is the
 * composite one: this stock, this buyable ({@see forBuyable()}).
 *
 * @extends Repository<StockItem>
 */
class StockItemRepository extends Repository
{
    protected string $model = StockItem::class;

    /**
     * Filter to the lines of one stock.
     *
     * @param Stock $stock
     * @return static
     */
    public function forStock(Stock $stock): static
    {
        $this->addCriteria(new Equals('stock', $stock->id));

        return $this;
    }

    /**
     * Filter to the single line holding a given buyable in a given stock.
     *
     * @param Stock $stock
     * @param BuyableInterface $buyable
     * @return static
     */
    public function forBuyable(Stock $stock, BuyableInterface $buyable): static
    {
        $this->forStock($stock);
        $this->addCriteria(new Equals('item_class', get_class($buyable)));
        $this->addCriteria(new Equals('item_id', $buyable->getId()));

        return $this;
    }
}
