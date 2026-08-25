<?php

namespace Tnt\Ecommerce\Cart;

use Closure;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Model\Customer;
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
use Tnt\Ecommerce\Tax\TaxPolicy;

/**
 * The shop's cart: what is in it, what it costs, and turning it into an order.
 *
 * Everything it knows about its own contents comes from a
 * {@see CartStorageInterface}, and nothing happens in its constructor. Between
 * them those two facts are what let a cart be built and exercised in a unit
 * test — hand it {@see InMemoryCartStorage} and the arithmetic below runs with
 * no session and no database anywhere near it.
 *
 * # Capabilities
 *
 * The cart is the one place that asks a buyable what it is capable of. Two
 * questions, one interface each, both answered by absence when the buyable does
 * not implement them:
 *
 * - {@see HasStockInterface} — {@see canAdd()} consults the buyable's stock;
 * - {@see TaxableInterface} — {@see getTax()} adds up the lines that carry a
 *   rate.
 *
 * Both checks are `instanceof`, deliberately, rather than a flag method on every
 * buyable. A buyable that has no stock and no tax implements neither and is
 * asked neither question — which is the whole point of the two interfaces, and
 * of retiring the `NullStockWorker` and `NullTaxRate` that used to stand in for
 * the answers.
 *
 * # Accounts
 *
 * {@see checkout()} asks a third question, of the shop rather than of a
 * buyable: whether anybody is signed in. {@see UserResolverInterface} answers
 * it with an id or with null, and the customer row records what it said. A shop
 * with no accounts leaves the default in place and every checkout is a guest
 * one. There is no second code path for that case — see `checkout()`.
 *
 * # Reporting, not deciding
 *
 * {@see canAdd()} says whether the stock covers a quantity, and {@see add()}
 * adds regardless. Refusing a sale the stock cannot fill today, taking it as a
 * backorder, and overselling on purpose to reconcile later are all real ways to
 * run a shop, and which one applies turns on facts a shop has and this package
 * has never been told. A package that picked one would be quietly wrong for
 * everyone it picked against, with no way for them to say so. So the figure is
 * reported and the shop acts on it.
 *
 * Tax used to stop in the same place, for the same reason, and it no longer
 * has to: a shop now *tells* this package the fact that was missing. See
 * {@see TaxPolicy} — with `ecommerce.prices` answered, {@see getTax()} is
 * exact rather than a guess, and {@see getTotal()} carries it when the
 * convention says the customer has not paid it yet. Nothing is inferred; a
 * shop that has said nothing gets inclusive prices and untaxed delivery, and
 * its totals do not move.
 */
class Cart implements CartInterface, TotalingInterface
{
    /**
     * The characters an order reference is built from.
     *
     * Crockford's base 32: the digits and the letters, less `I`, `L`, `O` and
     * `U`. See {@see newOrderReference()} for why a reference avoids them.
     */
    private const REFERENCE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * How many of those characters follow the row id.
     *
     * Ten of thirty-two is about 10^15 references per order id, which is far
     * past worth guessing at while still being short enough to read aloud.
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
     * Put a buyable in the cart, merging into the line it already has.
     *
     * Adds what it is asked to add; stock does not veto it. {@see canAdd()} is
     * how a shop asks first, and the note on this class is why that is an ask
     * rather than a gate.
     *
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return mixed|void
     */
    public function add(BuyableInterface $buyable, int $quantity = 1)
    {
        $this->storage->add($buyable, $quantity);
    }

    /**
     * Whether the stock would cover this buyable in this quantity.
     *
     * Always true for a buyable that is not counted: no stock means no limit,
     * and asking is the cart's job rather than the buyable's.
     *
     * For one that is, the figure checked is what the cart would *hold* — what
     * is already on its line plus what is being added — not the addition on its
     * own. Three added to a line of two is a request for five, and a stock of
     * four cannot fill it however the request was split up. What is already on
     * the line comes from {@see CartStorageInterface::quantityOf()}, so it is
     * the storage's idea of "the same buyable" that decides, which is the one
     * {@see add()} merges on.
     *
     * This reports; it does not gate — {@see add()} adds either way, for the
     * reason set out on this class. One thing worth knowing before acting on a
     * false: selling anyway is something the stock has to have been told about
     * too, because a {@see \Tnt\Ecommerce\Stock\StockWorker} refuses to go
     * below zero unless it was built to allow it.
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
        $total =
            $this->getSubTotal() +
            $this->getFulfillmentCost() -
            $this->getReduction();

        // Only when the prices it is built from are net. Under inclusive
        // pricing the tax is already inside every one of those figures, and
        // adding it here would charge the customer for it twice.
        if ($this->tax->addsTaxToTheTotal()) {
            $total += $this->getTax();
        }

        return $total;
    }

    /**
     * The tax on the lines that carry a rate, in cents.
     *
     * Each taxable line is taxed on its own line total and rounded there, which
     * is the per-line half of the rounding rule in {@see \Tnt\Ecommerce\Money}:
     * every figure below is one a customer could see printed against a line, so
     * the sum has to be the sum of the printed figures. Lines whose buyable does
     * not implement {@see TaxableInterface} contribute nothing, and a cart of
     * nothing but those answers 0.
     *
     * # The base is what is left after the discount
     *
     * A coupon comes off the cart, and tax is worked out per line, so the
     * reduction has to reach the lines before any of them is taxed. It is
     * spread across them in proportion to their totals, by
     * {@see \Tnt\Ecommerce\Money::apportion()}, so that the shares add back
     * up to it exactly — a cart discounted by 250 whose lines only lose 249
     * between them is a cent of tax charged on money nobody paid.
     *
     * Across *every* line, including the untaxed ones. The discount belongs to
     * the cart, so a share of it belongs to a line that pays no tax, and
     * charging the taxable lines with all of it would tax them on less than
     * the customer paid for them.
     *
     * # Whether it is in the total or on top of it
     *
     * That is the shop's {@see \Tnt\Ecommerce\Tax\PriceConvention}, and the
     * one fact this package cannot work out for itself. Under inclusive prices
     * this figure is a breakdown of {@see getTotal()} and the total does not
     * move; under exclusive prices it is an amount the customer has not paid
     * yet, and the total carries it.
     *
     * Delivery is taxed at the shop's own rate rather than the cart's — see
     * {@see TaxPolicy::taxOnDelivery()} for where that stops being exact.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getTax(): int
    {
        $items = $this->items();

        // Weighted by *every* line, including the untaxed ones the loop below
        // then skips. That reads like a bug from here and is the point; the
        // docblock above says why.
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
     * Turn the cart into an order placed by this customer.
     *
     * Guest and account checkout are the same call. The customer is whatever
     * the shop built and saved, the order carries it either way, and the only
     * difference between the two is what
     * {@see UserResolverInterface::getCurrentUserId()} answers — an id, or
     * null. Nothing below branches on it.
     *
     * The account link is attached first, before the order is written, because
     * the order points at the customer row and that row has to be finished by
     * then. {@see Customer::linkTo()} holds the rest of the rule.
     *
     * The `instanceof` is the same shape as the capability checks above: a shop
     * is free to pass any {@see CustomerInterface}, and one that is not this
     * package's {@see Customer} has no column to link and is left as it is.
     *
     * What the order freezes has grown by one more thing than the money: the
     * identity and the two addresses the checkout was made with, copied onto
     * the order's own columns rather than followed through the customer. That
     * is {@see Order::freezeCustomer()}, and the reason is the whole of
     * sc-11172 — an address book is edited, and an invoice is a statement about
     * the past.
     *
     * @param CustomerInterface $customer
     * @param (Closure(OrderInterface): void)|null $callback
     * @return OrderInterface
     */
    public function checkout(
        CustomerInterface $customer,
        ?Closure $callback = null
    ): OrderInterface {
        if ($customer instanceof Customer) {
            $customer->linkTo($this->users->getCurrentUserId());
        }

        $fulfillment = $this->getFulfillment();

        // Create the order
        $order = $this->newOrder();
        $order->created = time();
        $order->updated = time();
        $order->total = $this->getTotal();
        $order->subtotal = $this->getSubTotal();
        $order->reduction = $this->getReduction();
        $order->fulfillment_cost = $this->getFulfillmentCost();
        $order->tax = $this->getTax();

        // The convention travels with the order, not just the figures it
        // produced. A shop that switches from inclusive to exclusive prices
        // next year must still be able to reprint this invoice as it was
        // charged, and "was this total gross or net" is not recoverable from
        // the numbers alone.
        $order->prices = $this->tax->convention()->value;
        $order->fulfillment_method = $fulfillment?->getId();
        $order->discount = $this->getDiscount();
        $order->customer = $customer;

        // Two records of the same person, and they are not redundant. The line
        // above is a foreign key and answers "whose account is this order on",
        // reading the customer row as it stands today. This one takes the
        // order's own copy of who placed it and where it went, so that editing
        // or deleting an address book entry afterwards cannot reach an order
        // that has already been placed. Only the copy is safe on an invoice.
        // See Order::freezeCustomer().
        $order->freezeCustomer($customer);

        $order->save();

        // Generate an order id
        $order->order_id = $this->newOrderReference((int) $order->id);
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

    /**
     * The empty order {@see checkout()} is about to fill in.
     *
     * The one thing in `checkout()` that reaches for the database before it has
     * done any of its own work, and therefore the smallest place to stand a test
     * on. Override it with an {@see Order} whose `save()` and `add()` keep to
     * memory, and everything after this line — the timestamps, the four money
     * fields, the fulfillment method, the discount, the customer, the lines,
     * the event, the payment — runs for real with no connection anywhere near
     * it.
     *
     * Deliberately below the assignment rather than above it. A seam that
     * handed back a *finished* order would be the easier thing to write and
     * would test nothing: the code it replaced is the code worth checking.
     * `checkout()` freezes four money values onto a row by hand, and if two of
     * them were transposed no other test in this package would notice.
     *
     * Deliberately `protected`, and deliberately not an interface. Nothing has
     * asked to swap order creation in a running shop, and inventing a public
     * seam for a need nobody has is how a package grows surface it then has to
     * keep. If a real one ever turns up, this is where it starts.
     *
     * @return Order
     */
    protected function newOrder(): Order
    {
        return new Order();
    }

    /**
     * The reference a customer quotes when they mean this order.
     *
     * The row id, then a dash, then {@see REFERENCE_LENGTH} characters drawn
     * from {@see REFERENCE_ALPHABET} — `12-K4M7QX9RTB`. The id makes it unique
     * without a lookup; the rest makes it not worth guessing at.
     *
     * # It is a reference, not a key to the order
     *
     * Knowing it must not be enough to see an order. It is unguessable so that
     * a shop's orders cannot be walked through one after another, not so that
     * it can stand in for signing in. A page that shows an order still has to
     * establish who is asking, exactly as it would if the reference were `1`,
     * `2`, `3`. Anything else makes every email that quotes it a credential.
     *
     * # Why it is drawn the way it is
     *
     * `random_int()` rather than `rand()`. `rand()` is a Mersenne Twister, and
     * its next output can be worked out from enough of its previous ones, so a
     * reference built from it is predictable to somebody who has placed a few
     * orders of their own — no guessing required. That, rather than the number
     * of characters, was what was actually wrong with the old references.
     *
     * One segment of a fixed length. The generator this replaces split eight
     * characters as `rand(5, 8)` and the remainder, which added no randomness
     * at all: it only moved the underscore, and left the tail empty roughly one
     * order in four, so those references ended on a bare `_` and looked
     * truncated to whoever read them.
     *
     * An alphabet without `I`, `L`, `O` or `U`. A reference gets read down a
     * telephone and copied off a printed invoice, so a `0` that might be an `O`
     * costs somebody a support call. `U` is left out too, which is what keeps
     * a random string from occasionally spelling something regrettable.
     *
     * @param int $orderId The row id the first save handed back.
     * @return string
     *
     * @throws \Random\RandomException If the system has no source of secure
     *                                 randomness. Deliberately not caught:
     *                                 falling back to a predictable reference
     *                                 would defeat the point of this method.
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
