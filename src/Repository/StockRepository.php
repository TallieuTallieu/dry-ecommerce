<?php

namespace Tnt\Ecommerce\Repository;

use Tnt\Dbi\Criteria\Equals;
use Tnt\Ecommerce\Model\Stock;

/**
 * Reads `ecommerce_stock`.
 *
 * A shop can keep several stocks — a warehouse, a shop floor — and addresses
 * them by their human id rather than their primary key, so {@see byHid()} is
 * the lookup {@see \Tnt\Ecommerce\Stock\StockWorker} is built on.
 *
 * @extends Repository<Stock>
 */
class StockRepository extends Repository
{
    protected string $model = Stock::class;

    /**
     * Filter by human id.
     *
     * @param string $hid
     * @return static
     */
    public function byHid(string $hid): static
    {
        $this->addCriteria(new Equals('hid', $hid));

        return $this;
    }
}
