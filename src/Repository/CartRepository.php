<?php

namespace Tnt\Ecommerce\Repository;

use Tnt\Ecommerce\Model\Cart;

/**
 * Reads `ecommerce_cart`.
 *
 * The only lookup a shop ever does here is "the cart with this id", because the
 * session — not a query — decides which cart is the visitor's. Everything else
 * hangs off that row.
 *
 * @extends Repository<Cart>
 */
class CartRepository extends Repository
{
    protected string $model = Cart::class;
}
