<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Cart\CartRelease;
use Tnt\Ecommerce\Model\Cart as CartModel;
use Tnt\Ecommerce\Model\Order;

/**
 * The real {@see CartRelease} with its one query replaced: `cartOf()` answers
 * from a property instead of `ecommerce_cart`. Bound in bootEcommerce() so
 * every provider-booted test can dispatch Paid with the container stopped —
 * with no cart set, releasing is the ordinary no-op of an order whose cart
 * is already gone.
 */
final class FakeCartRelease extends CartRelease
{
    /**
     * The cart the "table" holds, if the test put one there.
     */
    public ?InMemoryCart $cart = null;

    /**
     * Every order id the release step looked a cart up for.
     *
     * @var array<int, int>
     */
    public array $lookedUp = [];

    /**
     * @param Order $order
     * @return CartModel|null
     */
    protected function cartOf(Order $order): ?CartModel
    {
        $this->lookedUp[] = (int) $order->id;

        // The same scope the production query has: notDeleted().
        if ($this->cart === null || $this->cart->deleted !== null) {
            return null;
        }

        return $this->cart;
    }
}
