<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Address\AddressType;
use Tnt\Ecommerce\Cart\LineOptions;
use Tnt\Ecommerce\Address\FrozenAddress;
use Tnt\Ecommerce\Contracts\AddressInterface;
use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Contracts\CustomerInterface;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Contracts\HasAddressesInterface;
use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Contracts\OrderItemInterface;
use Tnt\Ecommerce\Contracts\TotalingInterface;
use Tnt\Ecommerce\Facade\Shop;
use Tnt\Ecommerce\Order\OrderState;
use Tnt\Ecommerce\Payment\EntryKind;
use Tnt\Ecommerce\Payment\PaymentStatus;
use Tnt\Ecommerce\Repository\PaymentEntryRepository;
use Tnt\Ecommerce\Tax\PriceConvention;

/**
 * An order, as stored in `ecommerce_order` — a draft being filled in, or a
 * placed one. Totals, identity and addresses are frozen onto the row at
 * placement rather than recomputed; {@see $customer} is the account link
 * (null for a guest), the frozen columns are what an invoice prints. Money
 * columns are integer cents. See docs/orders.md.
 *
 * @see \Tnt\Ecommerce\Money
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property string|null $order_id
 * @property string|null $payment_id
 * @property int|null $total
 * @property int|null $subtotal
 * @property int|null $reduction
 * @property int|null $fulfillment_cost
 * @property int|null $tax
 * @property string|null $prices
 * @property string $state
 * @property string|null $payment_status
 * @property string|int|null $fulfillment_method
 * @property string|null $fulfillment_attributes
 * @property DiscountCode|null $discount
 * @property CustomerInterface|null $customer
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $company
 * @property string|null $vat
 * @property string|null $billing_street
 * @property string|null $billing_number
 * @property string|null $billing_box
 * @property string|null $billing_postal_code
 * @property string|null $billing_city
 * @property string|null $billing_country
 * @property string|null $shipping_street
 * @property string|null $shipping_number
 * @property string|null $shipping_box
 * @property string|null $shipping_postal_code
 * @property string|null $shipping_city
 * @property string|null $shipping_country
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
     * Write one cart line onto this order, as the order's own copy: the frozen
     * line total plus the canonical options ({@see LineOptions}; NULL when the
     * line had none).
     *
     * The parent/child link is NOT copied here: a child may be frozen
     * before its parent has a row, so {@see \Tnt\Ecommerce\Cart\Cart::place()}
     * writes it in a second pass over the lines this returns.
     *
     * @param CartItemInterface $cartItem
     * @return OrderItem
     */
    public function add(CartItemInterface $cartItem): OrderItem
    {
        $item = $this->newOrderItem();
        $item->created = time();
        $item->updated = time();
        $item->order = $this;
        $item->quantity = $cartItem->getQuantity();
        $item->price = $cartItem->getPrice();
        $item->item_id = (int) $cartItem->getBuyable()->getId();
        $item->item_class = get_class($cartItem->getBuyable());
        $item->options = LineOptions::canonical($cartItem->getOptions());
        $item->save();

        return $item;
    }

    /**
     * The empty line {@see add()} is about to fill in — a test seam, same
     * shape as {@see \Tnt\Ecommerce\Cart\Cart::newOrder()}.
     *
     * @return OrderItem
     */
    protected function newOrderItem(): OrderItem
    {
        return new OrderItem();
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
     * The account link, or null for a guest order. The frozen columns below —
     * not this row — are what an invoice prints.
     *
     * @return CustomerInterface|null
     */
    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    /**
     * Take this order's own copy of who placed it and where it goes — an
     * address book is edited, and an invoice is a statement about the past.
     * A missing address freezes blank; nothing is substituted. Null freezes
     * NOTHING: a draft's identity columns were written progressively and must
     * not be blanked by a place-step with no customer. See docs/addresses.md.
     *
     * @param CustomerInterface|null $customer
     * @return void
     */
    public function freezeCustomer(?CustomerInterface $customer): void
    {
        if ($customer === null) {
            return;
        }

        $this->first_name = $customer->getFirstName();
        $this->last_name = $customer->getLastName();
        $this->email = $customer->getEmail();
        $this->company = $customer->getCompanyName();
        $this->vat = $customer->getVatNumber();

        $book = $customer instanceof HasAddressesInterface ? $customer : null;

        foreach (AddressType::cases() as $type) {
            $snapshot = $type->snapshotOf($book?->getAddress($type));

            foreach ($snapshot as $column => $value) {
                $this->{$column} = $value;
            }
        }
    }

    /**
     * The company this order was placed under, or '' — frozen at checkout.
     *
     * @return string
     */
    public function getCompanyName(): string
    {
        return (string) $this->company;
    }

    /**
     * The VAT number this order was placed under, or '' — frozen at checkout.
     *
     * @return string
     */
    public function getVatNumber(): string
    {
        return (string) $this->vat;
    }

    /**
     * The billing address as this order froze it — a {@see FrozenAddress},
     * never the live row. All fields blank for a pre-address-book order;
     * {@see FrozenAddress::isEmpty()} is how to ask.
     *
     * @return AddressInterface
     */
    public function getBillingAddress(): AddressInterface
    {
        return new FrozenAddress(
            AddressType::Billing,
            (string) $this->billing_street,
            (string) $this->billing_number,
            (string) $this->billing_box,
            (string) $this->billing_postal_code,
            (string) $this->billing_city,
            (string) $this->billing_country
        );
    }

    /**
     * The shipping address as this order froze it. Written out column by
     * column on purpose — a generated list feeding five positional strings is
     * one silent transposition away from a wrong label.
     *
     * @return AddressInterface
     */
    public function getShippingAddress(): AddressInterface
    {
        return new FrozenAddress(
            AddressType::Shipping,
            (string) $this->shipping_street,
            (string) $this->shipping_number,
            (string) $this->shipping_box,
            (string) $this->shipping_postal_code,
            (string) $this->shipping_city,
            (string) $this->shipping_country
        );
    }

    /**
     * The first name the order was placed under.
     *
     * @return string
     */
    public function getFirstName(): string
    {
        return (string) $this->first_name;
    }

    /**
     * The last name the order was placed under.
     *
     * @return string
     */
    public function getLastName(): string
    {
        return (string) $this->last_name;
    }

    /**
     * The email address the order was placed with.
     *
     * @return string
     */
    public function getEmail(): string
    {
        return (string) $this->email;
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
     * The fulfillment attributes this order was placed with — the order's own
     * frozen copy, never read back through the method. Empty when nothing was
     * frozen. See docs/fulfillment.md.
     *
     * @return array<string, mixed>
     */
    public function getFulfillmentAttributes(): array
    {
        $raw = $this->fulfillment_attributes;

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        // A column that is not a JSON object reads as empty, not as an error.
        if (!is_array($decoded)) {
            return [];
        }

        $attributes = [];

        foreach ($decoded as $name => $value) {
            $attributes[(string) $name] = $value;
        }

        return $attributes;
    }

    /**
     * One frozen fulfillment attribute, or null when the order has none by
     * that name — null, never the MissingAttribute the live method throws.
     *
     * @param string $name
     * @return mixed
     */
    public function getFulfillmentAttribute(string $name): mixed
    {
        return $this->getFulfillmentAttributes()[$name] ?? null;
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
        return (int) $this->total;
    }

    /**
     * @return int
     */
    public function getSubTotal(): int
    {
        return (int) $this->subtotal;
    }

    /**
     * @return int
     */
    public function getReduction(): int
    {
        return (int) $this->reduction;
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
        return (int) $this->tax;
    }

    /**
     * Whether the amounts on this order contain their tax — frozen at
     * checkout, not read from configuration. Pre-column rows read as inclusive.
     *
     * @return PriceConvention
     */
    public function getPriceConvention(): PriceConvention
    {
        return PriceConvention::tryFrom((string) $this->prices) ??
            PriceConvention::Inclusive;
    }

    /**
     * Draft or placed. A column this package cannot read — legacy `''`, or an
     * unknown word — reads as {@see OrderState::Placed}: every order from
     * before the state existed was a real one, exactly as
     * {@see getPaymentStatus()} reads legacy rows as the status that claims
     * nothing. See docs/orders.md.
     *
     * @return OrderState
     */
    public function getState(): OrderState
    {
        return OrderState::tryFrom((string) $this->state) ?? OrderState::Placed;
    }

    /**
     * Whether {@see \Tnt\Ecommerce\Cart\Cart::place()} would accept this
     * existing order again: placed, and either nothing was ever captured or
     * every cent captured went back. A €0 capture counts — a free order is
     * paid. The one spelling of that rule; place()'s own guard reads through
     * it. A draft is placeable but not RE-placeable. See docs/orders.md.
     *
     * @return bool
     */
    public function isRePlaceable(): bool
    {
        if ($this->getState() !== OrderState::Placed) {
            return false;
        }

        if (!$this->hasCapture()) {
            return true;
        }

        return $this->getPaid() > 0 && $this->getNet() <= 0;
    }

    /**
     * The payment ledger's entries for this order, oldest first, across
     * every attempt. A test seam as well: override it to keep to memory. An
     * order never saved has none.
     *
     * @return list<PaymentEntry>
     */
    public function getPaymentEntries(): array
    {
        if ($this->id === null) {
            return [];
        }

        $entries = [];

        foreach (
            PaymentEntryRepository::create()->forOrder($this)->all()
            as $entry
        ) {
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Whether any attempt captured money — even €0.
     *
     * @return bool
     */
    public function hasCapture(): bool
    {
        foreach ($this->getPaymentEntries() as $entry) {
            if ($entry->kind === EntryKind::Captured->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cents captured, on any attempt.
     *
     * @return int
     */
    public function getPaid(): int
    {
        return $this->sumOf([EntryKind::Captured->value => 1]);
    }

    /**
     * Cents that went back: refunds and chargebacks, less their reversals.
     *
     * @return int
     */
    public function getReturned(): int
    {
        return $this->sumOf([
            EntryKind::Refunded->value => 1,
            EntryKind::RefundReversed->value => -1,
            EntryKind::Chargeback->value => 1,
            EntryKind::ChargebackReversed->value => -1,
        ]);
    }

    /**
     * Cents the shop kept: paid less returned.
     *
     * @return int
     */
    public function getNet(): int
    {
        return $this->getPaid() - $this->getReturned();
    }

    /**
     * Cents still owed against the frozen total; never below zero.
     *
     * @return int
     */
    public function getOutstanding(): int
    {
        return max(0, $this->getTotal() - $this->getNet());
    }

    /**
     * The signed sum of the entries of the given kinds.
     *
     * @param array<string, int> $signs kind => +1 or -1
     * @return int
     */
    private function sumOf(array $signs): int
    {
        $sum = 0;

        foreach ($this->getPaymentEntries() as $entry) {
            $sum += ($signs[$entry->kind] ?? 0) * $entry->getAmount();
        }

        return $sum;
    }

    /**
     * Delete every line off this order. Re-placement re-freezes the same row,
     * so the lines about to be copied fresh must not stack on the old ones.
     * A test seam like {@see newOrderItem()} — override to keep to memory.
     *
     * @return void
     */
    public function clearItems(): void
    {
        /** @var OrderItem $item */
        foreach ($this->items as $item) {
            $item->delete();
        }
    }

    /**
     * Where the money for this order stands, as {@see
     * \Tnt\Ecommerce\Payment\PaymentLedger} last derived it from the
     * entries. A column this package cannot read — legacy `''`, or an unknown word — reads as
     * {@see PaymentStatus::Pending}, the one status that claims nothing.
     *
     * @return PaymentStatus
     */
    public function getPaymentStatus(): PaymentStatus
    {
        return PaymentStatus::tryFrom((string) $this->payment_status) ??
            PaymentStatus::Pending;
    }
}
