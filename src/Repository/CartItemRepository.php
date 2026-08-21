<?php

namespace Tnt\Ecommerce\Repository;

use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Dbi\Criteria\Equals;
use Tnt\Dbi\Criteria\OrderBy;
use Tnt\Ecommerce\Model\Cart;
use Tnt\Ecommerce\Model\CartItem;

/**
 * Reads `ecommerce_cart_item`.
 *
 * A cart line is identified by the cart it belongs to plus the buyable it
 * points at, which the table records as a class name and a foreign id rather
 * than a real foreign key — a product can be any model the project likes.
 * {@see forBuyable()} is that composite lookup, and it is the query the "add
 * one more of these" path depends on.
 *
 * @extends Repository<CartItem>
 */
class CartItemRepository extends Repository
{
    protected string $model = CartItem::class;

    /**
     * Oldest line first, so a cart reads back in the order it was filled.
     */
    protected function init(): void
    {
        $this->addCriteria(new OrderBy('id'));
    }

    /**
     * Filter to the lines of one cart.
     *
     * @param Cart $cart
     * @return static
     */
    public function forCart(Cart $cart): static
    {
        $this->addCriteria(new Equals('cart', $cart->id));

        return $this;
    }

    /**
     * Filter to the single line holding a given buyable.
     *
     * @param Cart $cart
     * @param BuyableInterface $buyable
     * @return static
     */
    public function forBuyable(Cart $cart, BuyableInterface $buyable): static
    {
        $this->forCart($cart);
        $this->addCriteria(new Equals('item_class', get_class($buyable)));
        $this->addCriteria(new Equals('item_id', $buyable->getId()));

        return $this;
    }
}
