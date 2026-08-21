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
     * Filter by the public order reference (`12-A1B2C3D4_E5F6`), not the
     * primary key.
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
