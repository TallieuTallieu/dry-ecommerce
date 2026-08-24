<?php

declare(strict_types=1);

namespace Tests\Support;

use LogicException;
use Tnt\Ecommerce\Cart\Cart;
use Tnt\Ecommerce\Model\Order;

/**
 * The real cart, checking out into an {@see InMemoryOrder}.
 *
 * The whole of the test seam: one overridden method, the same shape as
 * {@see CapturesRevisionSql} uses to read a revision's SQL without running it.
 * Everything else about the cart — its storage, its totals, its coupon and
 * fulfillment handling, the body of `checkout()` — is the production code.
 */
final class InMemoryOrderCart extends Cart
{
    /**
     * @var InMemoryOrder|null
     */
    private ?InMemoryOrder $order = null;

    /**
     * The order this cart checked out into.
     *
     * A method rather than a public property so that it is non-null to read: a
     * test asserting on the order it just placed should not have to say so
     * twice. Asserting before checking out is a mistake in the test, and this
     * says which mistake it is.
     *
     * @return InMemoryOrder
     */
    public function placed(): InMemoryOrder
    {
        if ($this->order === null) {
            throw new LogicException(
                'This cart has not checked out, so there is no order to read.'
            );
        }

        return $this->order;
    }

    /**
     * @return Order
     */
    protected function newOrder(): Order
    {
        return $this->order = new InMemoryOrder();
    }
}
