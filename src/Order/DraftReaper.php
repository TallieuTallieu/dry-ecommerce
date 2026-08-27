<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Order;

use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Repository\OrderRepository;

/**
 * Deletes draft orders nobody has touched within the cart lifetime. A draft
 * is touched on every progressive save, so its own `updated` is the
 * abandonment clock — deliberately not a join through the cart, because a
 * draft need not have a cart link yet. Placed orders are never candidates.
 * See docs/orders.md.
 */
class DraftReaper
{
    private int $lifetimeDays;

    /**
     * @param int $lifetimeDays `ecommerce.cart_lifetime` — the same clock the
     *                          cookie cart lives by.
     */
    public function __construct(int $lifetimeDays)
    {
        $this->lifetimeDays = $lifetimeDays;
    }

    /**
     * Delete every stale draft, lines first, and say how many went.
     *
     * @return int
     */
    public function reap(): int
    {
        $cutoff = time() - $this->lifetimeDays * 86400;
        $reaped = 0;

        foreach ($this->staleDrafts($cutoff) as $draft) {
            // Defensively: a draft is not supposed to have lines before
            // placement, but one that somehow does must not leave orphans.
            $draft->clearItems();
            $draft->delete();

            $reaped++;
        }

        return $reaped;
    }

    /**
     * The drafts past the cutoff. A test seam, same shape as
     * {@see \Tnt\Ecommerce\Cart\Cart::newOrder()} — the query is the only
     * line that needs a database.
     *
     * @param int $cutoff
     * @return iterable<int, Order>
     */
    protected function staleDrafts(int $cutoff): iterable
    {
        return OrderRepository::create()
            ->drafts()
            ->updatedBefore($cutoff)
            ->all();
    }
}
