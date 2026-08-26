<?php

namespace Tnt\Ecommerce\Repository;

use Tnt\Ecommerce\Cart\LineOptions;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Dbi\Criteria\Equals;
use Tnt\Dbi\Criteria\IsNull;
use Tnt\Dbi\Criteria\OrderBy;
use Tnt\Ecommerce\Model\Cart;
use Tnt\Ecommerce\Model\CartItem;

/**
 * Reads `ecommerce_cart_item`. {@see forBuyable()} is the full merge-key
 * lookup; {@see forAnyVariantOf()} drops the options.
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
     * Filter to the single line holding a given buyable with given options —
     * the whole merge key. No options is stored as NULL, and `= NULL` matches
     * nothing in SQL, so the empty selection is matched with `IS NULL`.
     *
     * @param Cart $cart
     * @param BuyableInterface $buyable
     * @param array<array-key, mixed> $options
     * @return static
     */
    public function forBuyable(
        Cart $cart,
        BuyableInterface $buyable,
        array $options = []
    ): static {
        $this->forAnyVariantOf($cart, $buyable);

        $canonical = LineOptions::canonical($options);

        $this->addCriteria(
            $canonical === null
                ? new IsNull('options')
                : new Equals('options', $canonical)
        );

        return $this;
    }

    /**
     * Filter to every line holding a given buyable, whatever its options —
     * the lookup behind stock counting and whole-buyable removal.
     *
     * @param Cart $cart
     * @param BuyableInterface $buyable
     * @return static
     */
    public function forAnyVariantOf(
        Cart $cart,
        BuyableInterface $buyable
    ): static {
        $this->forCart($cart);
        $this->addCriteria(new Equals('item_class', get_class($buyable)));
        $this->addCriteria(new Equals('item_id', $buyable->getId()));

        return $this;
    }
}
