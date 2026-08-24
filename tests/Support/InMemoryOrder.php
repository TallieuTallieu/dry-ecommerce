<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Model\Order;

/**
 * An order that keeps to memory instead of a table.
 *
 * A real {@see Order} constructs and takes field values with no connection —
 * only `save()` and `add()` reach for one — so overriding those two is enough
 * to run `Cart::checkout()` end to end in a unit test. Everything the method
 * assigns is assigned to a real `Order`, and read back off it here.
 *
 * The point of keeping this a subclass rather than a stand-in is that
 * `checkout()` must not know the difference. It sets the same properties
 * through the same `dry\orm\Model::__set()`, so a test on this order is a test
 * of the real assignment, not of a rehearsal of it.
 *
 * @see \Tests\Support\InMemoryOrderCart
 */
final class InMemoryOrder extends Order
{
    /**
     * How many times `save()` was called.
     *
     * `checkout()` saves twice, and the second one is not ceremony: `order_id`
     * is built from the id the first save hands back. A test that watched only
     * the final state could not tell that apart from a single save.
     *
     * @var int
     */
    public int $saveCount = 0;

    /**
     * The lines added to this order, in the order they arrived.
     *
     * @var array<int, CartItemInterface>
     */
    public array $lines = [];

    /**
     * Stands in for the auto-increment, so that `order_id` has an id to build
     * on and comes out the same on every run.
     *
     * @return mixed|void
     */
    public function save()
    {
        $this->saveCount++;

        if ($this->id === null) {
            $this->id = 1;
        }
    }

    /**
     * Keeps the cart line rather than copying it into an `ecommerce_order_item`
     * row.
     *
     * The copying itself — price, quantity, item id and class — belongs to
     * {@see Order::add()} and needs a connection, so what is checked here is
     * that `checkout()` hands every line over exactly once.
     *
     * @param CartItemInterface $cartItem
     * @return mixed|void
     */
    public function add(CartItemInterface $cartItem)
    {
        $this->lines[] = $cartItem;
    }
}
