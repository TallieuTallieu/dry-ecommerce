<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Oak\Dispatcher\Facade\Dispatcher;
use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Contracts\CustomerInterface;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Contracts\TotalingInterface;
use Tnt\Ecommerce\Events\Order\BuyableAdded;
use Tnt\Ecommerce\Facade\Shop;

class Order extends Model implements OrderInterface, TotalingInterface
{
    const TABLE = 'ecommerce_order';

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
        $item->item_id = $cartItem->getBuyable()->getId();
        $item->item_class = get_class($cartItem->getBuyable());
        $item->save();

        Dispatcher::dispatch(BuyableAdded::class, new BuyableAdded($this, $cartItem->getBuyable()));
    }

    /**
     * @return array|mixed
     */
    public function getItems()
    {
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
        return Shop::getFulfillment($this->fulfillment_method);
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