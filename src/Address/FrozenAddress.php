<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Address;

use Tnt\Ecommerce\Contracts\AddressInterface;

/**
 * An address an order already froze, read back off the order's own columns.
 *
 * Readonly, and built from seven strings rather than from a row: there is
 * nothing behind it to go and re-read, which is exactly the property that makes
 * it printable on an invoice. {@see \Tnt\Ecommerce\Model\Address} is the other
 * implementation of {@see AddressInterface} and is the opposite in every way
 * that matters here — it is a live row in a customer's address book, it changes
 * when they move house, and it can be deleted.
 *
 * Nothing constructs one but {@see \Tnt\Ecommerce\Model\Order::getBillingAddress()}
 * and {@see \Tnt\Ecommerce\Model\Order::getShippingAddress()}. An order placed
 * before this ticket has none of the columns, so every field comes back `''` —
 * see {@see \Tnt\Ecommerce\Model\Order::freezeCustomer()}.
 */
final class FrozenAddress implements AddressInterface
{
    /**
     * @param AddressType $type
     * @param string $firstName
     * @param string $lastName
     * @param string $street
     * @param string $number
     * @param string $postalCode
     * @param string $city
     * @param string $country
     */
    public function __construct(
        private readonly AddressType $type,
        private readonly string $firstName,
        private readonly string $lastName,
        private readonly string $street,
        private readonly string $number,
        private readonly string $postalCode,
        private readonly string $city,
        private readonly string $country
    ) {}

    /**
     * @return AddressType
     */
    public function getType(): AddressType
    {
        return $this->type;
    }

    /**
     * Always false. A frozen copy is never the default.
     *
     * The mark says which address to reach for at the *next* checkout, and
     * this one is not being reached for — it is the copy an order already
     * took. Answering true would let a copy on an invoice be mistaken for a
     * live entry in the book, which is the confusion the two classes exist to
     * keep apart.
     *
     * @return bool
     */
    public function isDefault(): bool
    {
        return false;
    }

    /**
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * @return string
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * @return string
     */
    public function getStreet(): string
    {
        return $this->street;
    }

    /**
     * @return string
     */
    public function getNumber(): string
    {
        return $this->number;
    }

    /**
     * @return string
     */
    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    /**
     * @return string
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * @return string
     */
    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * Whether the order recorded any address of this kind at all.
     *
     * An order whose shipping columns are all blank was placed without a
     * delivery address — a digital sale, a collection in person, or a shop that
     * only ever collected one address. Worth being able to ask before printing
     * an empty block on an invoice.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->firstName === '' &&
            $this->lastName === '' &&
            $this->street === '' &&
            $this->number === '' &&
            $this->postalCode === '' &&
            $this->city === '' &&
            $this->country === '';
    }
}
