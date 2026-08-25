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
     * Filter by the account the customer belongs to.
     *
     * The lookup that makes an address book a book. A shop with a signed-in
     * user finds the customer row already on that account and checks out with
     * it, so the addresses on it are the ones the customer added last time.
     * With a fresh row each time there is nothing for a book to accumulate
     * into, and `ecommerce_address` holds one order's addresses rather than a
     * person's.
     *
     * Safe in a way {@see byEmail()} is not, and for the same reason byEmail()
     * is not: an account has been proved, an email address has only been
     * typed. Reusing a row on the strength of a matching account is reusing it
     * for the person who signed in; doing it on a matching email would let
     * anybody check out as somebody else's address book.
     *
     * Guests have no account and get a fresh row every time. That is not a
     * degraded path — it is the only correct one, because there is nothing to
     * recognise a returning guest by that they could not have made up.
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
