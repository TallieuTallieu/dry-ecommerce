<?php

namespace Tnt\Ecommerce\Cart;

use Closure;
use Tnt\Ecommerce\AlreadyPaid;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Model\Customer;
use Tnt\Ecommerce\Order\OrderState;
use Oak\Dispatcher\Facade\Dispatcher;
use Tnt\Ecommerce\Model\DiscountCode;
use Tnt\Ecommerce\Events\Order\Created;
use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\ShopInterface;
use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Contracts\CouponInterface;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\PaymentInterface;
use Tnt\Ecommerce\Contracts\TaxableInterface;
use Tnt\Ecommerce\Contracts\CustomerInterface;
use Tnt\Ecommerce\Contracts\TotalingInterface;
use Tnt\Ecommerce\Contracts\HasStockInterface;
use Tnt\Ecommerce\Contracts\CartStorageInterface;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Contracts\UserResolverInterface;
use Tnt\Ecommerce\Money;
use Tnt\Ecommerce\Payment\PaymentStatus;
use Tnt\Ecommerce\Tax\TaxPolicy;

/**
 * The shop's cart: what is in it, what it costs, and turning it into an order.
 *
 * All contents live in a {@see CartStorageInterface} (hand it
 * {@see InMemoryCartStorage} for tests). Stock and tax are capabilities checked
 * with `instanceof` ({@see HasStockInterface}, {@see TaxableInterface}).
 * See docs/cart.md.
 */
class Cart implements CartInterface, TotalingInterface
{
    /**
     * Crockford's base 32 — no `I`, `L`, `O` or `U`, so a reference survives
     * being read down a telephone.
     */
    private const REFERENCE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * How many random characters follow the row id in a reference.
     */
    private const REFERENCE_LENGTH = 10;

    private ShopInterface $shop;

    private CartStorageInterface $storage;

    private PaymentInterface $payment;

    private UserResolverInterface $users;

    private TaxPolicy $tax;

    /**
     * @param ShopInterface $shop
     * @param CartStorageInterface $storage
     * @param PaymentInterface $payment
     * @param UserResolverInterface $users
     * @param TaxPolicy|null $tax How the shop taxes. Defaults to prices that
     *                            contain their tax and untaxed delivery, which
     *                            leaves an existing shop's totals unmoved.
     */
    public function __construct(
        ShopInterface $shop,
        CartStorageInterface $storage,
        PaymentInterface $payment,
        UserResolverInterface $users,
        ?TaxPolicy $tax = null
    ) {
        $this->shop = $shop;
        $this->storage = $storage;
        $this->payment = $payment;
        $this->users = $users;
        $this->tax = $tax ?? new TaxPolicy();
    }

    /**
     * Put a buyable in the cart, merging into the line with the same buyable
     * *and* options (canonical form — see docs/options.md). Stock does not
     * veto an add; {@see canAdd()} is how a shop asks first.
     *
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @param array<array-key, mixed> $options
     * @return mixed|void
     */
    public function add(
        BuyableInterface $buyable,
        int $quantity = 1,
        array $options = []
    ) {
        $this->storage->add($buyable, $quantity, $options);
    }

    /**
     * Whether the stock would cover what the cart would then hold of this
     * buyable — what is already on its lines plus $quantity. Always true for
     * an uncounted buyable. Reports only; {@see add()} adds regardless.
     *
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return bool
     */
    public function canAdd(BuyableInterface $buyable, int $quantity = 1): bool
    {
        if (!($buyable instanceof HasStockInterface)) {
            return true;
        }

        return $buyable
            ->getStockWorker()
            ->isAvailable(
                $buyable,
                $this->storage->quantityOf($buyable) + $quantity
            );
    }

    /**
     * Take a buyable out of the cart — every option-variant of it. One line is
     * what {@see removeItem()} is for.
     *
     * @param BuyableInterface $buyable
     * @return mixed|void
     */
    public function remove(BuyableInterface $buyable)
    {
        $this->storage->remove($buyable);
    }

    /**
     * Set one line to a quantity, by the line's own id. Zero or less removes
     * the line; an unknown id is a no-op.
     *
     * @param string $itemId
     * @param int $quantity
     * @return void
     */
    public function updateQuantity(string $itemId, int $quantity): void
    {
        $this->storage->updateQuantity($itemId, $quantity);
    }

    /**
     * Take one line out of the cart, by the line's own id. An unknown id is a
     * no-op — a stale basket form is ordinary, not an error.
     *
     * @param string $itemId
     * @return void
     */
    public function removeItem(string $itemId): void
    {
        $this->storage->removeItem($itemId);
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
        $total =
            $this->getSubTotal() +
            $this->getFulfillmentCost() -
            $this->getReduction();

        // Only under exclusive pricing — inclusive prices already contain
        // their tax, and adding it here would charge it twice.
        if ($this->tax->addsTaxToTheTotal()) {
            $total += $this->getTax();
        }

        return $total;
    }

    /**
     * The tax on the lines that carry a rate, in cents — per line, on what is
     * left of the line after its share of the discount, rounded per line.
     * Whether it is in the total or on top of it is the shop's
     * {@see \Tnt\Ecommerce\Tax\PriceConvention}. See docs/tax.md.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getTax(): int
    {
        $items = $this->items();

        // Weighted by *every* line, including the untaxed ones the loop below
        // skips: a share of the discount belongs to untaxed lines too, or the
        // taxable ones would be taxed on less than the customer paid.
        $shares = Money::apportion(
            $this->getReduction(),
            array_map(static fn($item): int => $item->getPrice(), $items)
        );

        $tax = 0;

        foreach ($items as $index => $item) {
            $buyable = $item->getBuyable();

            if (!($buyable instanceof TaxableInterface)) {
                continue;
            }

            $tax += $this->tax->taxOn(
                $item->getPrice() - $shares[$index],
                $buyable->getTaxRate()
            );
        }

        return $tax + $this->tax->taxOnDelivery($this->getFulfillmentCost());
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
     * Turn the cart into a placed order, in one go. Guest (null) and account
     * checkout are the same call; the order freezes its own copy of the money,
     * the identity, the addresses and the fulfillment attributes. See
     * docs/orders.md.
     *
     * @param CustomerInterface|null $customer
     * @param (Closure(OrderInterface): void)|null $callback
     * @return OrderInterface
     */
    public function checkout(
        ?CustomerInterface $customer = null,
        ?Closure $callback = null
    ): OrderInterface {
        $order = $this->newOrder();
        $order->created = time();

        return $this->place($order, $customer, $callback);
    }

    /**
     * Place an order from this cart — a fresh one ({@see checkout()}), a
     * draft a project filled in progressively, or a placed-but-unpaid order
     * being re-placed. Placement copies the lines, freezes the money, the
     * convention and the fulfillment method with its attributes, freezes the
     * identity IF a customer is given (a draft's own identity columns are
     * left standing otherwise), makes the reference if there is none yet,
     * sets the state placed, links the cart to the order, dispatches
     * {@see Created} and calls pay(). See docs/orders.md.
     *
     * @param Order $order
     * @param CustomerInterface|null $customer
     * @param (Closure(OrderInterface): void)|null $callback
     * @return OrderInterface
     *
     * @throws AlreadyPaid If money already arrived for this order —
     *                     re-freezing it would rewrite what was paid for.
     */
    public function place(
        Order $order,
        ?CustomerInterface $customer = null,
        ?Closure $callback = null
    ): OrderInterface {
        // One source of truth: a draft may always be placed (its money never
        // arrived — pay() only runs after placement), anything already placed
        // only while Order::isRePlaceable() says so. Loud rather than a
        // silent no-op — a shop about to re-freeze a paid order is about to
        // lose an invoice.
        if (
            $order->getState() !== OrderState::Draft &&
            !$order->isRePlaceable()
        ) {
            throw AlreadyPaid::order($order);
        }

        if ($customer instanceof Customer) {
            $customer->linkTo($this->users->getCurrentUserId());
        }

        $fulfillment = $this->getFulfillment();

        // Re-placement re-freezes the SAME row: its old lines go first, or
        // the copy below would stack a second basket on the first.
        if ($order->id !== null) {
            $order->clearItems();
        }

        $order->updated = time();
        $order->total = $this->getTotal();
        $order->subtotal = $this->getSubTotal();
        $order->reduction = $this->getReduction();
        $order->fulfillment_cost = $this->getFulfillmentCost();
        $order->tax = $this->getTax();

        // The convention travels with the order — "was this total gross or
        // net" is not recoverable from the numbers alone.
        $order->prices = $this->tax->convention()->value;
        $order->fulfillment_method = $fulfillment?->getId();

        // The order's own copy: the cart holding these dies long before
        // anybody fulfills the order.
        $order->fulfillment_attributes = self::frozenFulfillmentAttributes(
            $fulfillment
        );

        $order->state = OrderState::Placed->value;

        // Before pay() runs — a gateway only ever moves the status forward.
        // The guard above is what makes this bare write legal.
        $order->payment_status = PaymentStatus::Pending->value;

        $order->discount = $this->getDiscount();

        if ($customer !== null) {
            // The foreign key answers "whose account"; freezeCustomer() takes
            // the order's own copy of who placed it and where it went, the
            // only thing safe to print. With no customer both stay as the
            // draft wrote them — a guest draft already carries its identity.
            $order->customer = $customer;
            $order->freezeCustomer($customer);
        }

        $order->save();

        // The reference is made once and kept: a re-placed order stays the
        // order the customer was quoted.
        if ((string) $order->order_id === '') {
            $order->order_id = $this->newOrderReference((int) $order->id);
            $order->save();
        }

        // Copy every cart line onto the order
        foreach ($this->items() as $item) {
            $order->add($item);
        }

        // The cart→order link — what the Paid listener follows back to
        // soft-delete this cart once the money arrives.
        $this->storage->setOrderId((int) $order->id);

        // Created means placement — a re-placed order announces itself again,
        // so a listener on it must be idempotent per order id.
        Dispatcher::dispatch(Created::class, new Created($order));

        if ($callback) {
            call_user_func($callback, $order);
        }

        // Pay
        $this->payment->pay($order);

        return $order;
    }

    /**
     * The method's *required* attributes, as the JSON object the order will
     * carry; null when no method was chosen or it requires nothing. See
     * docs/fulfillment.md.
     *
     * @param FulfillmentInterface|null $fulfillment
     * @return string|null
     *
     * @throws \Tnt\Ecommerce\Fulfillment\MissingAttribute If a required
     *                                                     attribute was never
     *                                                     set.
     * @throws \JsonException If an attribute holds a value JSON cannot carry.
     */
    private static function frozenFulfillmentAttributes(
        ?FulfillmentInterface $fulfillment
    ): ?string {
        if ($fulfillment === null) {
            return null;
        }

        $required = $fulfillment->requireAttributes();

        if ($required === []) {
            return null;
        }

        $attributes = [];

        foreach ($required as $name) {
            $attributes[$name] = $fulfillment->getAttribute($name);
        }

        // The same canonical encoding the line options use — one JSON
        // convention per package, not two that disagree about escaping.
        return LineOptions::canonical($attributes);
    }

    /**
     * The empty order {@see checkout()} is about to fill in. A test seam:
     * override with an Order whose save() and add() keep to memory, and the
     * whole of checkout() runs without a database.
     *
     * @return Order
     */
    protected function newOrder(): Order
    {
        return new Order();
    }

    /**
     * The reference a customer quotes — `12-K4M7QX9RTB`. Unguessable to stop
     * enumeration, but not a credential: a page showing an order must still
     * establish who is asking. See docs/orders.md.
     *
     * @param int $orderId The row id the first save handed back.
     * @return string
     *
     * @throws \Random\RandomException If the system has no source of secure
     *                                 randomness — deliberately not caught.
     */
    private function newOrderReference(int $orderId): string
    {
        $reference = '';
        $last = strlen(self::REFERENCE_ALPHABET) - 1;

        for ($i = 0; $i < self::REFERENCE_LENGTH; $i++) {
            $reference .= self::REFERENCE_ALPHABET[random_int(0, $last)];
        }

        return $orderId . '-' . $reference;
    }
}
