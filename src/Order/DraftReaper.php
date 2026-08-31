<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Order;

use Tnt\Ecommerce\Model\Cart as CartModel;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Repository\CartRepository;
use Tnt\Ecommerce\Repository\OrderRepository;

/**
 * Deletes draft orders nobody has touched within the cart lifetime. A draft
 * is touched on every progressive save, so its own `updated` is the
 * abandonment clock — a draft need not have a cart link yet. But a living
 * cart pointing at the draft, itself touched after the cutoff, keeps it: a
 * basket still in use is not abandoned. Placed orders are never candidates.
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
            // The visitor may still be shopping: adding a line touches the
            // cart, not the draft. A living cart touched after the cutoff
            // spares its draft until the basket goes quiet too.
            $cart = $this->cartOf($draft);

            if ($cart !== null && (int) $cart->updated >= $cutoff) {
                continue;
            }

            // Defensively: a draft is not supposed to have lines before
            // placement, but one that somehow does must not leave orphans.
            $draft->clearItems();
            $draft->delete();

            $reaped++;
        }

        return $reaped;
    }

    /**
     * The living cart pointing at this draft, or null. A test seam, same
     * shape as {@see \Tnt\Ecommerce\Cart\CartRelease::cartOf()}.
     *
     * @param Order $draft
     * @return CartModel|null
     */
    protected function cartOf(Order $draft): ?CartModel
    {
        return CartRepository::create()
            ->byOrder((int) $draft->id)
            ->notDeleted()
            ->firstOrNull();
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
