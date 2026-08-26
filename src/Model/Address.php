<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Address\AddressType;
use Tnt\Ecommerce\Contracts\AddressInterface;
use Tnt\Ecommerce\NotAnAddressType;

/**
 * One address in a customer's address book, as stored in `ecommerce_address`.
 * Editable — which is why an order copies it at checkout rather than pointing
 * at it ({@see Order::freezeCustomer()}). See docs/addresses.md.
 *
 * @see \Tnt\Ecommerce\Address\FrozenAddress The other side of that copy.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property Customer $customer
 * @property string $type
 * @property int $is_default
 * @property string $street
 * @property string $number
 * @property string $postal_code
 * @property string $city
 * @property string $country
 */
class Address extends Model implements AddressInterface
{
    const TABLE = 'ecommerce_address';

    /**
     * @var array<string, string>
     */
    public static $special_fields = [
        'customer' => Customer::class,
    ];

    public function __toString(): string
    {
        $lines = array_filter([
            trim($this->getStreet() . ' ' . $this->getNumber()),
            trim($this->getPostalCode() . ' ' . $this->getCity()),
            $this->getCountry(),
        ]);

        return $lines === [] ? '#' . $this->id : implode(', ', $lines);
    }

    /**
     * What this address is for — refuses an unrecognised value rather than
     * guessing between invoice and parcel.
     *
     * @return AddressType
     * @throws NotAnAddressType If the column holds something else.
     */
    public function getType(): AddressType
    {
        $type = (string) $this->type;

        return AddressType::tryFrom($type) ?? throw new NotAnAddressType($type);
    }

    /**
     * Whether this is the one of its kind a checkout takes by default.
     *
     * @return bool
     */
    public function isDefault(): bool
    {
        return (int) $this->is_default === 1;
    }

    /**
     * @param AddressType $type
     * @return void
     */
    public function setType(AddressType $type): void
    {
        $this->type = $type->value;
    }

    /**
     * @return string
     */
    public function getStreet(): string
    {
        return (string) $this->street;
    }

    /**
     * @return string
     */
    public function getNumber(): string
    {
        return (string) $this->number;
    }

    /**
     * @return string
     */
    public function getPostalCode(): string
    {
        return (string) $this->postal_code;
    }

    /**
     * @return string
     */
    public function getCity(): string
    {
        return (string) $this->city;
    }

    /**
     * @return string
     */
    public function getCountry(): string
    {
        return (string) $this->country;
    }
}
