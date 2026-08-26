<?php

namespace Tnt\Ecommerce\Repository;

use Tnt\Dbi\Criteria\Equals;
use Tnt\Dbi\Criteria\OrderBy;
use Tnt\Ecommerce\Address\AddressType;
use Tnt\Ecommerce\Model\Address;
use Tnt\Ecommerce\Model\Customer;

/**
 * Reads `ecommerce_address` — for queries across customers; one customer's
 * book is {@see \Tnt\Ecommerce\Model\Customer::getAddresses()}.
 *
 * @extends Repository<Address>
 */
class AddressRepository extends Repository
{
    protected string $model = Address::class;

    /**
     * Most recently created address first.
     */
    protected function init(): void
    {
        $this->addCriteria(new OrderBy('created', 'DESC'));
    }

    /**
     * Filter to one customer's book.
     *
     * @param Customer $customer
     * @return static
     */
    public function ofCustomer(Customer $customer): static
    {
        $this->addCriteria(new Equals('customer', $customer->id));

        return $this;
    }

    /**
     * Filter to addresses of one kind.
     *
     * @param AddressType $type
     * @return static
     */
    public function ofType(AddressType $type): static
    {
        $this->addCriteria(new Equals('type', $type->value));

        return $this;
    }
}
