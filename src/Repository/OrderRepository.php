<?php

namespace Tnt\Ecommerce\Repository;

use Tnt\Dbi\Criteria\Equals;
use Tnt\Dbi\Criteria\OrderBy;
use Tnt\Ecommerce\Model\Customer;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Payment\PaymentStatus;

/**
 * Reads `ecommerce_order`.
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
     * key. Finding an order by it does not establish that the person asking
     * is entitled to see it.
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
     * Filter by payment status. A legacy order reads back as pending through
     * {@see Order::getPaymentStatus()} but its column holds '', so filtering
     * on {@see PaymentStatus::Pending} will not find it.
     *
     * @param PaymentStatus|string $status
     * @return static
     */
    public function withPaymentStatus(PaymentStatus|string $status): static
    {
        $this->addCriteria(
            new Equals(
                'payment_status',
                $status instanceof PaymentStatus ? $status->value : $status
            )
        );

        return $this;
    }
}
