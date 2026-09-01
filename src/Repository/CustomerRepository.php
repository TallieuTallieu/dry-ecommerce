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
     * Filter by the account the customer belongs to — the lookup that makes
     * an address book a book, and the find behind
     * {@see \Tnt\Ecommerce\Model\Customer::forUser()}. At most one row
     * matches (UNIQUE on `user`). Safe in a way {@see byEmail()} is not: an
     * account has been proved, an email has only been typed.
     *
     * @param int $userId
     * @return static
     */
    public function byUser(int $userId): static
    {
        $this->addCriteria(new Equals('user', $userId));

        return $this;
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
