<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Address;

use Tnt\Ecommerce\Contracts\AddressInterface;

/**
 * An address an order already froze, read back off the order's own columns —
 * readonly, with nothing behind it to re-read, which is what makes it
 * printable on an invoice. The live counterpart is
 * {@see \Tnt\Ecommerce\Model\Address}.
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
     * Always false — a frozen copy is never the default; the mark belongs to
     * the live book.
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
     * Whether the order recorded any address of this kind at all — worth
     * asking before printing an empty block on an invoice.
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
