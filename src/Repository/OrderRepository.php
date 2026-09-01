<?php

namespace Tnt\Ecommerce\Repository;

use Tnt\Dbi\Criteria\Equals;
use Tnt\Dbi\Criteria\LessThan;
use Tnt\Dbi\Criteria\NotEquals;
use Tnt\Dbi\Criteria\OrderBy;
use Tnt\Dbi\QueryBuilder;
use Tnt\Ecommerce\Model\Customer;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Order\OrderState;
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
     * An account's orders, in one query: an inner join through the account's
     * single `ecommerce_customer` row (UNIQUE on `user`, so the join cannot
     * fan out). Composes with the other scopes — a customer-facing history
     * is `forUser($id)->placed()`. See docs/orders.md.
     *
     * @param int $userId
     * @return static
     */
    public function forUser(int $userId): static
    {
        $this->useQueryBuilder(function (QueryBuilder $query): void {
            $query
                ->innerJoin('ecommerce_customer')
                ->on('ecommerce_customer.id', '=', 'ecommerce_order.customer');
        });

        $this->addCriteria(new Equals('ecommerce_customer.user', $userId));

        return $this;
    }

    /**
     * Only placed orders. Every list an admin or a customer sees should go
     * through this: a draft is a half-filled form, not an order, and must not
     * show up anywhere an order does. Spelled "not draft" rather than
     * "= placed" so legacy rows — whose column holds '' but which were all
     * real orders — stay in. See docs/orders.md.
     *
     * @return static
     */
    public function placed(): static
    {
        $this->addCriteria(new NotEquals('state', OrderState::Draft->value));

        return $this;
    }

    /**
     * Only drafts — what the reaper deletes, and nothing a list should show.
     *
     * @return static
     */
    public function drafts(): static
    {
        $this->addCriteria(new Equals('state', OrderState::Draft->value));

        return $this;
    }

    /**
     * Orders not touched since a moment. A draft is touched on every
     * progressive save, so its own `updated` is its abandonment clock.
     *
     * @param int $timestamp Unix time; strictly before it matches.
     * @return static
     */
    public function updatedBefore(int $timestamp): static
    {
        $this->addCriteria(new LessThan('updated', $timestamp));

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
