<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Contracts\CustomerInterface;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Contracts\OrderItemInterface;
use Tnt\Ecommerce\Contracts\TotalingInterface;
use Tnt\Ecommerce\Facade\Shop;

/**
 * A placed order, as stored in `ecommerce_order`.
 *
 * Totals are frozen onto the row at checkout rather than recomputed, so an
 * order still reads back the way it was placed after prices, coupons or
 * fulfillment costs change.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property string $order_id
 * @property string $payment_id
 * @property float $total
 * @property float $subtotal
 * @property float $reduction
 * @property float $fulfillment_cost
 * @property string $payment_status
 * @property string|int|null $fulfillment_method
 * @property DiscountCode|null $discount
 * @property CustomerInterface $customer
 * @property-read \dry\orm\relationship\HasMany $items
 */
class Order extends Model implements OrderInterface, TotalingInterface
{
    const TABLE = 'ecommerce_order';

    /**
     * @var array<string, string>
     */
    public static $special_fields = [
        'customer' => Customer::class,
        'discount' => DiscountCode::class,
    ];

    /**
     * @param CartItemInterface $cartItem
     * @return mixed|void
     */
    public function add(CartItemInterface $cartItem)
    {
        $item = new OrderItem();
        $item->order = $this;
        $item->quantity = $cartItem->getQuantity();
        $item->price = $cartItem->getPrice();
        $item->item_id = (int) $cartItem->getBuyable()->getId();
        $item->item_class = get_class($cartItem->getBuyable());
        $item->save();
    }

    /**
     * @return iterable<int, OrderItemInterface>
     */
    public function getItems()
    {
        /** @var iterable<int, OrderItemInterface> */
        return $this->items;
    }

    /**
     * @param CustomerInterface $customer
     * @return mixed|void
     */
    public function setCustomer(CustomerInterface $customer)
    {
        $this->customer = $customer;
        $this->save();
    }

    /**
     * @return CustomerInterface
     */
    public function getCustomer(): CustomerInterface
    {
        return $this->customer;
    }

    /**
     * @param FulfillmentInterface $fulfillmentMethod
     * @return mixed|void
     */
    public function setFulfillment(FulfillmentInterface $fulfillmentMethod)
    {
        $this->fulfillment_method = $fulfillmentMethod->getId();
        $this->save();
    }

    /**
     * @return FulfillmentInterface
     */
    public function getFulfillment(): FulfillmentInterface
    {
        return Shop::getFulfillment($this->fulfillment_method ?? '');
    }

    /**
     * @return \dry\orm\relationship\HasMany
     */
    public function get_items()
    {
        return $this->has_many(OrderItem::class, 'order');
    }

    /**
     * @return float
     */
    public function getTotal(): float
    {
        return $this->total;
    }

    /**
     * @return float
     */
    public function getSubTotal(): float
    {
        return $this->subtotal;
    }

    /**
     * @return float
     */
    public function getReduction(): float
    {
        return $this->reduction;
    }
}
