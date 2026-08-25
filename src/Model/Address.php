<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Address\AddressType;
use Tnt\Ecommerce\Contracts\AddressInterface;
use Tnt\Ecommerce\NotAnAddressType;

/**
 * One address in a customer's address book, as stored in `ecommerce_address`.
 *
 * # A list, not two sets of columns
 *
 * `ecommerce_customer` used to carry twelve inline columns — five `address_*`
 * and seven `shipping_*` — which gave a customer exactly one billing and one
 * shipping address and no way to have a second of either. A person with a home
 * and a work address, or a company delivering to three sites, could not be
 * expressed at all. That is what this table replaces.
 *
 * # It is editable, and that is why an order does not point at it
 *
 * A row here says where a customer lives *today*. They move house, they correct
 * a typo, they delete an address they no longer use, and all three are ordinary
 * things for an address book to allow. None of them may reach an order that has
 * already been placed, so an order does not hold a foreign key into this table
 * — it copies the seven fields onto itself at checkout. See
 * {@see Order::freezeCustomer()} for the whole of that argument.
 *
 * @see \Tnt\Ecommerce\Address\FrozenAddress The other side of that copy.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property Customer $customer
 * @property string $type
 * @property int $is_default
 * @property string $first_name
 * @property string $last_name
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
            trim($this->getFirstName() . ' ' . $this->getLastName()),
            trim($this->getStreet() . ' ' . $this->getNumber()),
            trim($this->getPostalCode() . ' ' . $this->getCity()),
            $this->getCountry(),
        ]);

        return $lines === [] ? '#' . $this->id : implode(', ', $lines);
    }

    /**
     * What this address is for.
     *
     * Refuses a value it does not recognise rather than defaulting to one of
     * the two. Which kind an address is decides whether it ends up on an
     * invoice or on a parcel, and both are wrong half the time if guessed.
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
     * Stored as an int rather than a bool because the column is one, and read
     * through a comparison rather than a cast so that a row written before the
     * column existed reads as "not the default" instead of as a truthy string.
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
    public function getFirstName(): string
    {
        return (string) $this->first_name;
    }

    /**
     * @return string
     */
    public function getLastName(): string
    {
        return (string) $this->last_name;
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
