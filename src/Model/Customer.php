<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Contracts\CustomerInterface;

/**
 * The person an order was placed by, as stored in `ecommerce_customer`.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $address_street
 * @property string $address_number
 * @property string $address_postal_code
 * @property string $address_city
 * @property string $address_country
 * @property string $shipping_first_name
 * @property string $shipping_last_name
 * @property string $shipping_street
 * @property string $shipping_number
 * @property string $shipping_postal_code
 * @property string $shipping_city
 * @property string $shipping_country
 * @property string $vat
 * @property string $comments
 * @property string $first_contact
 */
class Customer extends Model implements CustomerInterface
{
    const TABLE = 'ecommerce_customer';

    public function __toString(): string
    {
        if ($this->getFirstName() && $this->getLastName()) {
            return $this->getFirstName() . ' ' . $this->getLastName();
        }

        if ($this->getFirstName()) {
            return $this->getFirstName();
        }

        if ($this->getLastName()) {
            return $this->getLastName();
        }

        return '#' . $this->id;
    }

    public function getFirstName(): string
    {
        return $this->first_name;
    }

    public function getLastName(): string
    {
        return $this->last_name;
    }
}
