<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Address\AddressType;
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
use Tnt\Ecommerce\Tax\PriceConvention;

/**
 * A placed order, as stored in `ecommerce_order`.
 *
 * Totals are frozen onto the row at checkout rather than recomputed, so an
 * order still reads back the way it was placed after prices, coupons or
 * fulfillment costs change. All four money columns are integer cents.
 *
 * # Two records of the customer, answering two questions
 *
 * {@see $customer} is a foreign key, and it answers "whose account is this
 * order on" — follow it and you get the row as it stands today, which is what a
 * shop wants when it lists a person's orders.
 *
 * The `first_name`, `last_name`, `email`, `billing_*` and `shipping_*` columns
 * are a copy taken at checkout, and they answer "who placed it and where did it
 * go". Only these are safe to print on an invoice. See
 * {@see freezeCustomer()}.
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
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $billing_first_name
 * @property string $billing_last_name
 * @property string $billing_street
 * @property string $billing_number
 * @property string $billing_postal_code
 * @property string $billing_city
 * @property string $billing_country
 * @property string $shipping_first_name
 * @property string $shipping_last_name
 * @property string $shipping_street
 * @property string $shipping_number
 * @property string $shipping_postal_code
 * @property string $shipping_city
 * @property string $shipping_country
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
     * Take this order's own copy of who placed it and where it goes.
     *
     * # Why a copy and not a foreign key
     *
     * The obvious design is two more foreign keys, into
     * {@see \Tnt\Ecommerce\Model\Address}, and it is wrong for one reason that
     * is not recoverable from afterwards: those rows are an address *book*, and
     * a book is edited. A customer moves house and corrects the address they
     * keep on file; a customer deletes the address they used once for a gift.
     * Both are things an address book must let them do, and an order that
     * pointed at those rows would answer, next year, that last year's parcel
     * went to an address it never went to — or that it went nowhere, the row
     * having been deleted.
     *
     * An invoice is a statement about the past. A mutable row cannot back one.
     * So the seven fields of each address are copied here, as text, and the
     * only thing that can ever change them is a deliberate write to this order.
     *
     * The same argument applies to the name and the email, one step further
     * removed: {@see $customer} does point at a live row, and following it is
     * the right thing to do to answer *whose account this is*. It is the wrong
     * thing to do to answer *who placed this* — a shop that ever lets somebody
     * change the name or email on their account would be rewriting the header
     * of every invoice they had ever been sent.
     *
     * # What a missing address does
     *
     * Nothing is substituted. A customer with a shipping address and no billing
     * address freezes seven blank billing columns, and vice versa: an order
     * that carries an address the customer never gave for that purpose is a
     * worse record than one that admits the purpose had no address. A shop that
     * means "bill me where you ship" says so by putting an address of each kind
     * in the book, which is the thing the book is now able to express.
     *
     * A customer that is not a {@see HasAddressesInterface} at all — a shop's
     * own customer class, or anything selling what is never delivered — freezes
     * both sides blank and is asked nothing. Same `instanceof` shape as the
     * capability checks in {@see \Tnt\Ecommerce\Cart\Cart}, and the same reason:
     * absence of a thing is expressed by absence.
     *
     * @param CustomerInterface $customer
     * @return void
     */
    public function freezeCustomer(CustomerInterface $customer): void
    {
        $this->first_name = $customer->getFirstName();
        $this->last_name = $customer->getLastName();
        $this->email = $customer->getEmail();

        $book = $customer instanceof HasAddressesInterface ? $customer : null;

        foreach (AddressType::cases() as $type) {
            $snapshot = $type->snapshotOf($book?->getAddress($type));

            foreach ($snapshot as $column => $value) {
                $this->{$column} = $value;
            }
        }
    }

    /**
     * The billing address as this order froze it.
     *
     * A {@see FrozenAddress}, never the row it was copied from — reading it
     * cannot reach the address book, which is the point. An order placed before
     * sc-11172 has none of these columns and comes back with every field blank;
     * {@see FrozenAddress::isEmpty()} is how to ask.
     *
     * @return AddressInterface
     */
    public function getBillingAddress(): AddressInterface
    {
        return new FrozenAddress(
            AddressType::Billing,
            (string) $this->billing_first_name,
            (string) $this->billing_last_name,
            (string) $this->billing_street,
            (string) $this->billing_number,
            (string) $this->billing_postal_code,
            (string) $this->billing_city,
            (string) $this->billing_country
        );
    }

    /**
     * The shipping address as this order froze it.
     *
     * Written out column by column rather than built from
     * {@see AddressType::columns()}, unlike {@see freezeCustomer()}. The write
     * side has to agree with the schema and gains from being generated; the
     * read side has to agree with a constructor of seven positional strings,
     * and a generated list feeding that is one silent transposition away from
     * printing the postcode as the house number.
     *
     * @return AddressInterface
     */
    public function getShippingAddress(): AddressInterface
    {
        return new FrozenAddress(
            AddressType::Shipping,
            (string) $this->shipping_first_name,
            (string) $this->shipping_last_name,
            (string) $this->shipping_street,
            (string) $this->shipping_number,
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
