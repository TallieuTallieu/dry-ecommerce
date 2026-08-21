<?php

namespace Tnt\Ecommerce\Cart;

use Closure;
use dry\util\Str;
use Tnt\Ecommerce\Model\Order;
use Oak\Dispatcher\Facade\Dispatcher;
use Tnt\Ecommerce\Model\DiscountCode;
use Tnt\Ecommerce\Events\Order\Created;
use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\ShopInterface;
use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Contracts\CouponInterface;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\PaymentInterface;
use Tnt\Ecommerce\Contracts\CustomerInterface;
use Tnt\Ecommerce\Contracts\TotalingInterface;
use Tnt\Ecommerce\Contracts\CartStorageInterface;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;

/**
 * The shop's cart: what is in it, what it costs, and turning it into an order.
 *
 * Everything it knows about its own contents comes from a
 * {@see CartStorageInterface}, and nothing happens in its constructor. Between
 * them those two facts are what let a cart be built and exercised in a unit
 * test — hand it {@see InMemoryCartStorage} and the arithmetic below runs with
 * no session and no database anywhere near it.
 */
class Cart implements CartInterface, TotalingInterface
{
    private ShopInterface $shop;

    private CartStorageInterface $storage;

    private PaymentInterface $payment;

    /**
     * @param ShopInterface $shop
     * @param CartStorageInterface $storage
     * @param PaymentInterface $payment
     */
    public function __construct(
        ShopInterface $shop,
        CartStorageInterface $storage,
        PaymentInterface $payment
    ) {
        $this->shop = $shop;
        $this->storage = $storage;
        $this->payment = $payment;
    }

    /**
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return mixed|void
     */
    public function add(BuyableInterface $buyable, int $quantity = 1)
    {
        $this->storage->add($buyable, $quantity);
    }

    /**
     * @param BuyableInterface $buyable
     * @return mixed|void
     */
    public function remove(BuyableInterface $buyable)
    {
        $this->storage->remove($buyable);
    }

    /**
     * @return array<int, \Tnt\Ecommerce\Contracts\CartItemInterface>
     */
    public function items(): array
    {
        return $this->storage->items();
    }

    /**
     * @return mixed|void
     */
    public function clear()
    {
        $this->storage->clear();
    }

    /**
     * @param FulfillmentInterface $fulfillment
     * @return mixed|void
     */
    public function setFulfillment(FulfillmentInterface $fulfillment)
    {
        if (!$this->shop->hasFulfillment($fulfillment->getId())) {
            return;
        }

        $this->storage->setFulfillmentId($fulfillment->getId());
    }

    /**
     * @return null|FulfillmentInterface
     */
    public function getFulfillment(): ?FulfillmentInterface
    {
        $id = $this->storage->getFulfillmentId();

        if (!$id || !$this->shop->hasFulfillment($id)) {
            return null;
        }

        return $this->shop->getFulfillment($id);
    }

    /**
     * @return int
     */
    public function getFulfillmentCost(): int
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
            $this->storage->setDiscount($discount);
        }
    }

    /**
     * The coupon actually in force, or null.
     *
     * A code can be applied and then stop being redeemable — it expires, it
     * runs out, the cart drops below its threshold — so the stored code is
     * re-checked on every read rather than trusted.
     *
     * @return CouponInterface|null
     */
    private function getCoupon(): ?CouponInterface
    {
        $discount = $this->storage->getDiscount();

        if ($discount === null) {
            return null;
        }

        $coupon = $discount->coupon;

        if ($coupon === null || !$coupon->isRedeemable($this)) {
            return null;
        }

        return $coupon;
    }

    /**
     * @return null|DiscountCode
     */
    public function getDiscount(): ?DiscountCode
    {
        if ($this->getCoupon() === null) {
            return null;
        }

        return $this->storage->getDiscount();
    }

    /**
     * The sum of the line totals, in cents.
     *
     * Every term is an integer, so the accumulation below is exact however many
     * lines it runs over — which is the reason money is not a float here.
     *
     * @return int
     */
    public function getSubTotal(): int
    {
        $cost = 0;

        foreach ($this->items() as $item) {
            $cost += $item->getPrice();
        }

        return $cost;
    }

    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->getSubTotal() +
            $this->getFulfillmentCost() -
            $this->getReduction();
    }

    /**
     * @return int
     */
    public function getReduction(): int
    {
        $coupon = $this->getCoupon();

        if ($coupon === null) {
            return 0;
        }

        return $coupon->getReduction($this);
    }

    /**
     * @param CustomerInterface $customer
     * @param (Closure(OrderInterface): void)|null $callback
     * @return OrderInterface
     */
    public function checkout(
        CustomerInterface $customer,
        ?Closure $callback = null
    ): OrderInterface {
        $fulfillment = $this->getFulfillment();

        // Create the order
        $order = new Order();
        $order->created = time();
        $order->updated = time();
        $order->total = $this->getTotal();
        $order->subtotal = $this->getSubTotal();
        $order->reduction = $this->getReduction();
        $order->fulfillment_cost = $this->getFulfillmentCost();
        $order->fulfillment_method = $fulfillment?->getId();
        $order->discount = $this->getDiscount();
        $order->customer = $customer;
        $order->save();

        // Generate an order id
        $start = rand(5, 8);
        $rest = 8 - $start;

        $order->order_id =
            $order->id . '-' . Str::random($start) . '_' . Str::random($rest);
        $order->save();

        // Add all items to the order
        foreach ($this->items() as $item) {
            $order->add($item);
        }

        // Dispatch an order created event
        Dispatcher::dispatch(Created::class, new Created($order));

        if ($callback) {
            call_user_func($callback, $order);
        }

        // Pay
        $this->payment->pay($order);

        return $order;
    }
}
