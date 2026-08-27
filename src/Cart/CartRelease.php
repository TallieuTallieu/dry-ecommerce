<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Cart;

use Tnt\Ecommerce\Model\Cart as CartModel;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Repository\CartRepository;

/**
 * Soft-deletes the cart behind a paid order. The provider's Paid listener
 * calls this: the money arrived, so the basket has become the order and must
 * stop being anybody's cart — but the row keeps its order link forever, which
 * is the provenance it survives for. See docs/payment.md.
 */
class CartRelease
{
    /**
     * @param Order $order
     * @return void
     */
    public function release(Order $order): void
    {
        if ($order->id === null) {
            return;
        }

        $cart = $this->cartOf($order);

        if ($cart === null) {
            return;
        }

        $cart->deleted = time();
        $cart->save();
    }

    /**
     * The living cart pointing at this order, or null. A test seam, same
     * shape as {@see Cart::newOrder()} — the query is the only line that
     * needs a database.
     *
     * @param Order $order
     * @return CartModel|null
     */
    protected function cartOf(Order $order): ?CartModel
    {
        return CartRepository::create()
            ->byOrder((int) $order->id)
            ->notDeleted()
            ->firstOrNull();
    }
}
