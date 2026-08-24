<?php

namespace Tnt\Ecommerce\Repository;

use Tnt\Dbi\Criteria\Equals;
use Tnt\Dbi\Criteria\OrderBy;
use Tnt\Ecommerce\Model\Customer;
use Tnt\Ecommerce\Model\Order;

/**
 * Reads `ecommerce_order`.
 *
 * Two lookups matter outside the checkout itself: the public order reference
 * that a payment provider or a customer quotes back at you
 * ({@see byOrderId()}), and the payment provider's own reference, which is what
 * a webhook arrives carrying ({@see byPaymentId()}).
 *
 * @extends Repository<Order>
 */
class OrderRepository extends Repository
{
    protected string $model = Order::class;

    /**
     * Most recent order first.
     */
    protected function init(): void
    {
        $this->addCriteria(new OrderBy('created', 'DESC'));
    }

    /**
     * Filter by the public order reference (`12-K4M7QX9RTB`), not the primary
     * key.
     *
     * The reference is unguessable so that a shop's orders cannot be walked
     * through one by one, but it is **not** a credential: finding an order by
     * it does not establish that the person asking is entitled to see it. See
     * {@see \Tnt\Ecommerce\Cart\Cart::newOrderReference()}.
     *
     * @param string $orderId
     * @return static
     */
    public function byOrderId(string $orderId): static
    {
        $this->addCriteria(new Equals('order_id', $orderId));

        return $this;
    }

    /**
     * Filter by the reference the payment provider knows the order as.
     *
     * @param string $paymentId
     * @return static
     */
    public function byPaymentId(string $paymentId): static
    {
        $this->addCriteria(new Equals('payment_id', $paymentId));

        return $this;
    }

    /**
     * Filter to one customer's orders.
     *
     * @param Customer $customer
     * @return static
     */
    public function forCustomer(Customer $customer): static
    {
        $this->addCriteria(new Equals('customer', $customer->id));

        return $this;
    }

    /**
     * Filter by payment status.
     *
     * @param string $status
     * @return static
     */
    public function withPaymentStatus(string $status): static
    {
        $this->addCriteria(new Equals('payment_status', $status));

        return $this;
    }
}
