<?php

namespace Tnt\Ecommerce\Repository;

use Tnt\Dbi\Criteria\Equals;
use Tnt\Dbi\Criteria\OrderBy;
use Tnt\Ecommerce\Model\Customer;

/**
 * Reads `ecommerce_customer`.
 *
 * {@see byEmail()} exists so an admin can find the orders placed from an
 * address. It is deliberately not used by checkout: an email address is not an
 * identity claim, and matching on one would let anyone check out as someone
 * else.
 *
 * @extends Repository<Customer>
 */
class CustomerRepository extends Repository
{
    protected string $model = Customer::class;

    /**
     * Most recently created customer first.
     */
    protected function init(): void
    {
        $this->addCriteria(new OrderBy('created', 'DESC'));
    }

    /**
     * Filter by email address.
     *
     * @param string $email
     * @return static
     */
    public function byEmail(string $email): static
    {
        $this->addCriteria(new Equals('email', trim($email)));

        return $this;
    }
}
