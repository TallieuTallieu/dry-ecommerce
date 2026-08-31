<?php

namespace Tnt\Ecommerce\Repository;

use Tnt\Dbi\Criteria\Equals;
use Tnt\Dbi\Criteria\IsNull;
use Tnt\Ecommerce\Model\Cart;

/**
 * Reads `ecommerce_cart`.
 *
 * The visitor's own cart is not found here — the session or the cookie says
 * which row is theirs (see docs/cart.md). What is queried is the afterlife:
 * the cart behind an order ({@see byOrder()}), a token's row
 * ({@see byToken()}), and the living subset ({@see notDeleted()}).
 *
 * @extends Repository<Cart>
 */
class CartRepository extends Repository
{
    protected string $model = Cart::class;

    /**
     * Filter by the cart's token — what the cookie holds, never the row id.
     *
     * @param string $token
     * @return static
     */
    public function byToken(string $token): static
    {
        $this->addCriteria(new Equals('token', $token));

        return $this;
    }

    /**
     * Filter to the cart(s) pointing at one order — how the Paid listener
     * finds the basket to soft-delete. See docs/payment.md.
     *
     * @param int $orderId The order's row id, not its public reference.
     * @return static
     */
    public function byOrder(int $orderId): static
    {
        $this->addCriteria(new Equals('order', $orderId));

        return $this;
    }

    /**
     * Only carts that have not been soft-deleted. Every lookup that answers
     * "the visitor's cart" scopes with this — a soft-deleted cart is absent
     * everywhere, kept only for provenance.
     *
     * @return static
     */
    public function notDeleted(): static
    {
        $this->addCriteria(new IsNull('deleted'));

        return $this;
    }
}
