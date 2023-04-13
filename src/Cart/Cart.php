<?php

namespace Tnt\Ecommerce\Cart;

use Closure;
use dry\db\FetchException;
use Tnt\Ecommerce\Model\Order;
use Oak\Session\Facade\Session;
use Tnt\Ecommerce\Model\CartItem;
use Oak\Dispatcher\Facade\Dispatcher;
use Tnt\Ecommerce\Model\DiscountCode;
use Tnt\Ecommerce\Events\Order\Created;
use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\ShopInterface;
use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\PaymentInterface;
use Tnt\Ecommerce\Contracts\CustomerInterface;
use Tnt\Ecommerce\Contracts\TotalingInterface;
use Oak\Contracts\Container\ContainerInterface;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;

/**
 * Class Cart
 * @package Tnt\Ecommerce\Cart
 */
class Cart implements CartInterface, TotalingInterface
{
    /**
     * @var ContainerInterface $app
     */
    private $app;

    /**
     * @var ShopInterface $shop
     */
    private $shop;

    /**
     * @var \Tnt\Ecommerce\Model\Cart $cart
     */
    private $cart;

    /**
     * Cart constructor.
     * @param ContainerInterface $app
     * @param ShopInterface $shop
     */
    public function __construct(ContainerInterface $app, ShopInterface $shop)
    {
        $this->app = $app;
        $this->shop = $shop;
        $this->restore();
    }

    /**
     * Restores the cart
     */
    private function restore()
    {
        if (Session::has('cart')) {
            try {
                $this->cart = \Tnt\Ecommerce\Model\Cart::load(Session::get('cart'));
                return;
            } catch (FetchException $e) {}
        }

        $cart = new \Tnt\Ecommerce\Model\Cart();
        $cart->created = time();
        $cart->updated = time();
        $cart->save();

        Session::set('cart', $cart->id);
        Session::save();

        $this->cart = $cart;
    }

    /**
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return mixed|void
     */
    public function add(BuyableInterface $buyable, int $quantity = 1)
    {
        $item_class = get_class($buyable);

        try {
            $cart_item = CartItem::load_by([
                'cart' => $this->cart->id,
                'item_id' => $buyable->getId(),
                'item_class' => $item_class,
            ]);

            $cart_item->setQuantity($cart_item->getQuantity() + $quantity);

        } catch (FetchException $e) {

            $cart_item = new CartItem();
            $cart_item->created = time();
            $cart_item->updated = time();
            $cart_item->cart = $this->cart->id;
            $cart_item->item_id = $buyable->getId();
            $cart_item->item_class = $item_class;
            $cart_item->quantity = $quantity;
            $cart_item->save();
        }
    }

    /**
     * @param BuyableInterface $buyable
     * @return mixed|void
     */
    public function remove(BuyableInterface $buyable)
    {
        $item_class = get_class($buyable);

        try {
            $cart_item = CartItem::load_by([
                'cart' => $this->cart->id,
                'item_id' => $buyable->getId(),
                'item_class' => $item_class,
            ]);

            $cart_item->delete();

        } catch (FetchException $e) {
            //
        }
    }

    /**
     * @return array
     */
    public function items(): array
    {
        return $this->cart->items->to_array();
    }

    /**
     * @return mixed|void
     */
    public function clear()
    {
        if ($this->cart) {
            $this->cart->delete();
        }

        Session::set('cart', null);
        Session::save();
    }

    /**
     * @param FulfillmentInterface $fulfillment
     * @return mixed|void
     */
    public function setFulfillment(FulfillmentInterface $fulfillment)
    {
        if (! $this->shop->hasFulfillment($fulfillment->getId())) {
            return;
        }

        $this->cart->fulfillment_method = $fulfillment->getId();
        $this->cart->save();
    }

    /**
     * @return null|FulfillmentInterface
     */
    public function getFulfillment(): ?FulfillmentInterface
    {
        $id = $this->cart->fulfillment_method;

        if (! $id || ! $this->shop->hasFulfillment($id)) {
            return null;
        }

        return $this->shop->getFulfillment($id);
    }

    /**
     * @return float
     */
    public function getFulfillmentCost(): float
    {
        $fulfill = $this->getFulfillment();

        if ($fulfill) {
            return $fulfill->getCost($this);
        }

        return 0;
    }

    /**
     * @param DiscountCode $discount
     * @return mixed|void
     */
    public function addDiscount(DiscountCode $discount)
    {
        $coupon = $discount->coupon;

        if ($coupon && $coupon->isRedeemable()) {

            $this->cart->discount = $discount;
            $this->cart->save();
        }
    }

    /**
     * @return null|DiscountCode
     */
    public function getDiscount(): ?DiscountCode
    {
        $discount = $this->cart->discount;

        if (! $discount) {
            return null;
        }

        $coupon = $discount->coupon;

        if (! $coupon || ! $coupon->isRedeemable($this)) {
            return null;
        }

        return $this->cart->discount;
    }

    /**
     * @return float
     */
    public function getSubTotal(): float
    {
        $cost = 0;

        foreach ($this->items() as $item) {
            $cost += $item->getPrice();
        }

        return $cost;
    }

    /**
     * @return float
     */
    public function getTotal(): float
    {
        $total = $this->getSubTotal() + $this->getFulfillmentCost();

        if (($discount = $this->getDiscount())) {
            $total = $total - $discount->coupon->getReduction($this);
        }

        return $total;
    }

    /**
     * @return float
     */
    public function getReduction(): float
    {
        $total = $this->getSubTotal() + $this->getFulfillmentCost();

        if (($discount = $this->getDiscount())) {
            return $discount->coupon->getReduction($this);
        }

        return 0;
    }

    /**
     * @param CustomerInterface $customer
     * @return OrderInterface
     */
    public function checkout(CustomerInterface $customer, Closure $callback = null): OrderInterface
    {
        // Create the order
        $order = new Order();
        $order->created = time();
        $order->updated = time();
        $order->total = $this->getTotal();
        $order->subtotal = $this->getSubTotal();
        $order->reduction = $this->getReduction();
        $order->fulfillment_cost = $this->getFulfillmentCost();
        $order->fulfillment_method = ($this->getFulfillment() ? $this->getFulfillment()->getId() : null);
        $order->discount = $this->getDiscount();
        $order->customer = $customer;
        $order->save();

        // Generate an order id
        $start = rand(5, 8);
        $rest = 8 - $start;

        $order->order_id = $order->id.'-'.\dry\util\string\random($start).'_'.\dry\util\string\random($rest);
        $order->save();

        // Add all items to the order
        foreach ($this->items() as $item) {
            $order->add($item);
        }

        // Dispatch an order created event
        Dispatcher::dispatch(Created::class, new Created($order));

        if($callback){
            call_user_func($callback, $order);
        }

        // Pay
        $this->app->get(PaymentInterface::class)->pay($order);

        return $order;
    }
}