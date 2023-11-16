<?php

namespace Tnt\Ecommerce\Order;

use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\CustomerInterface;
use Tnt\Ecommerce\Contracts\OrderFactoryInterface;
use Tnt\Ecommerce\Contracts\OrderIdGeneratorInterface;
use Tnt\Ecommerce\Model\Order;

class OrderFactory implements OrderFactoryInterface
{
    /**
     * @var OrderIdGeneratorInterface $idGenerator
     */
    private $idGenerator;

    /**
     * OrderFactory constructor.
     * @param OrderIdGeneratorInterface $idGenerator
     */
    public function __construct(OrderIdGeneratorInterface $idGenerator)
    {
        $this->idGenerator = $idGenerator;
    }

    public function create(CartInterface $cart, CustomerInterface $customer): Order
    {
        // Create the order
        $order = new Order();
        $order->created = time();
        $order->updated = time();
        $order->total = $cart->getTotal();
        $order->subtotal = $cart->getSubTotal();
        $order->reduction = $cart->getReduction();
        $order->fulfillment_cost = $cart->getFulfillmentCost();
        $order->fulfillment_method = ($cart->getFulfillment() ? $cart->getFulfillment()->getId() : null);
        $order->discount = $cart->getDiscount();
        $order->customer = $customer;
        $order->save();

        // Generate order id
        $order->order_id = $this->idGenerator->generate($order);
        $order->save();

        // Add all items to the order
        foreach ($cart->items() as $item) {
            $order->add($item);
        }

        return $order;
    }
}