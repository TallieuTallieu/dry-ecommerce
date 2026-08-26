<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Model\OrderItem;

/**
 * An order line that keeps to memory instead of a row.
 *
 * The counterpart to {@see InMemoryOrder}, one level down: a real
 * {@see OrderItem} constructs and takes field values with no connection —
 * only `save()` reaches for one — so overriding it is enough to run
 * {@see \Tnt\Ecommerce\Model\Order::add()} for real. Everything `add()`
 * assigns is assigned through the same `dry\orm\Model::__set()` and read back
 * off the same columns, so a test on this line is a test of the real copying,
 * not of a rehearsal of it.
 *
 * @see InMemoryLineOrder
 */
final class InMemoryOrderItem extends OrderItem
{
    /**
     * How many times `save()` was called. One per line, in `add()`.
     *
     * @var int
     */
    public int $saveCount = 0;

    /**
     * @return mixed|void
     */
    public function save()
    {
        $this->saveCount++;
    }
}
