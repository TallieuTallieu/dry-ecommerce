<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Model\OrderItem;

/**
 * A real order whose lines keep to memory.
 *
 * The other half of what {@see InMemoryOrder} tests: that one overrides
 * `add()` wholesale to check checkout hands every line over, so the *copying*
 * inside `add()` — price, quantity, item id, class, options, six assignments
 * onto a row by hand — never ran under test. This one overrides only the two
 * seams (`save()`, and `newOrderItem()` in the same shape as
 * `Cart::newOrder()`), so `add()` runs its production body against a line
 * that stays out of the database and the assignments themselves become
 * checkable.
 */
final class InMemoryLineOrder extends Order
{
    /**
     * The lines `add()` wrote, in the order it wrote them.
     *
     * @var array<int, InMemoryOrderItem>
     */
    public array $writtenLines = [];

    /**
     * @return mixed|void
     */
    public function save() {}

    /**
     * @return OrderItem
     */
    protected function newOrderItem(): OrderItem
    {
        $item = new InMemoryOrderItem();
        $this->writtenLines[] = $item;

        return $item;
    }
}
