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
use Tnt\Ecommerce\Tax\PriceConvention;

/**
 * A placed order, as stored in `ecommerce_order`.
 *
 * Totals are frozen onto the row at checkout rather than recomputed, so an
 * order still reads back the way it was placed after prices, coupons or
 * fulfillment costs change. All four money columns are integer cents.
 *
 * @see \Tnt\Ecommerce\Money
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property string $order_id
 * @property string $payment_id
 * @property int $total
 * @property int $subtotal
 * @property int $reduction
 * @property int $fulfillment_cost
 * @property int $tax
 * @property string $prices
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
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return int
     */
    public function getSubTotal(): int
    {
        return $this->subtotal;
    }

    /**
     * @return int
     */
    public function getReduction(): int
    {
        return $this->reduction;
    }

    /**
     * The tax frozen onto the order at checkout, in cents.
     *
     * Whether the customer paid this on top of {@see getTotal()} or inside it
     * is what {@see getPriceConvention()} records.
     *
     * @return int
     */
    public function getTax(): int
    {
        return $this->tax;
    }

    /**
     * Whether the amounts on this order contain their tax.
     *
     * Frozen at checkout rather than read from configuration, so that an order
     * still reads back the way it was placed after the shop changes how it
     * quotes prices. A row written before this column existed has no answer
     * and reports the inclusive convention, which is the one whose totals
     * match what those rows already hold.
     *
     * @return PriceConvention
     */
    public function getPriceConvention(): PriceConvention
    {
        return PriceConvention::tryFrom((string) $this->prices) ??
            PriceConvention::Inclusive;
    }
}
