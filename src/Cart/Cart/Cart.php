<?php

namespace Tnt\Ecommerce\Cart;

use dry\db\FetchException;
use Oak\Contracts\Dispatcher\DispatcherInterface;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\CartFactoryInterface;
use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\CartStorageInterface;
use Tnt\Ecommerce\Contracts\CustomerInterface;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Contracts\OrderFactoryInterface;
use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Contracts\PaymentInterface;
use Tnt\Ecommerce\Contracts\ShopInterface;
use Tnt\Ecommerce\Contracts\TotalingInterface;
use Tnt\Ecommerce\Events\Cart\BuyableAdded;
use Tnt\Ecommerce\Events\Cart\BuyableRemoved;
use Tnt\Ecommerce\Events\Cart\DiscountAdded;
use Tnt\Ecommerce\Events\Order\Created;
use Tnt\Ecommerce\Model\CartItem;
use Tnt\Ecommerce\Model\DiscountCode;

/**
 * Class Cart
 * @package Tnt\Ecommerce\Cart
 */
class Cart implements CartInterface, TotalingInterface
{
    /**
     * @var ShopInterface $shop
     */
    private $shop;

    /**
     * @var \Tnt\Ecommerce\Model\Cart $cart
     */
    private $cart;

    /**
     * @var PaymentInterface $payment
     */
    private $payment;

    /**
     * @var CartStorageInterface $cartStorage
     */
    private $cartStorage;

    /**
     * @var CartFactoryInterface $cartFactory
     */
    private $cartFactory;

    /**
     * @var OrderFactoryInterface $orderFactory
     */
    private $orderFactory;

    /**
     * @var DispatcherInterface $dispatcher
     */
    private $dispatcher;

    /**
     * Cart constructor.
     * @param ShopInterface $shop
     * @param PaymentInterface $payment
     * @param CartStorageInterface $cartStorage
     * @param CartFactoryInterface $cartFactory
     * @param OrderFactoryInterface $orderFactory
     * @param DispatcherInterface $dispatcher
     */
    public function __construct(
        ShopInterface $shop,
        PaymentInterface $payment,
        CartStorageInterface $cartStorage,
        CartFactoryInterface $cartFactory,
        OrderFactoryInterface $orderFactory,
        DispatcherInterface $dispatcher
    )
    {
        $this->shop = $shop;
        $this->payment = $payment;
        $this->cartStorage = $cartStorage;
        $this->cartFactory = $cartFactory;
        $this->orderFactory = $orderFactory;
        $this->dispatcher = $dispatcher;
        $this->restore();
    }

    /**
     * Restores the cart
     */
    private function restore()
    {
        $this->cart = $this->cartStorage->retrieve();

        if (! $this->cart) {

            $this->cart = $this->cartFactory->create();
            $this->cartStorage->store($this->cart);
        }
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

            // Dispatch a buyable added event
            $this->dispatcher->dispatch(BuyableAdded::class, new BuyableAdded($this, $buyable));
        }
    }

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

            // Dispatch a buyable removed event
            $this->dispatcher->dispatch(BuyableRemoved::class, new BuyableRemoved($this, $buyable));

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

        $this->cartStorage->clear();
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

        if ($coupon && $coupon->isRedeemable($this)) {

            $this->cart->discount = $discount;
            $this->cart->save();

            // Dispatch a discount added event
            $this->dispatcher->dispatch(DiscountAdded::class, new DiscountAdded($this, $discount));
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
        if (($discount = $this->getDiscount())) {
            return $discount->coupon->getReduction($this);
        }

        return 0;
    }

    /**
     * @param CustomerInterface $customer
     * @return OrderInterface
     */
    public function checkout(CustomerInterface $customer): OrderInterface
    {
        // Create the order
        $order = $this->orderFactory->create($this, $customer);

        // Dispatch an order created event
        $this->dispatcher->dispatch(Created::class, new Created($order));

        // Pay
        $this->payment->pay($order);

        // Give back the order
        return $order;
    }

    /**
     * @return mixed
     */
    public function getIdentifier()
    {
        return $this->cart->getIdentifier();
    }
}
