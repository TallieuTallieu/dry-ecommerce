<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Contracts\CustomerInterface;

/**
 * The person an order was placed by, as stored in `ecommerce_customer`.
 *
 * # A row per checkout, not a row per person
 *
 * A customer row is a record of who placed one order and where it was going. It
 * is not a profile, and nothing in this package looks one up to reuse it —
 * {@see \Tnt\Ecommerce\Repository\CustomerRepository::byEmail()} says why
 * matching on an address would be wrong, and there is a second reason besides:
 * this row carries the delivery address, the VAT number and the comments *of
 * that order*. Reusing one across checkouts would rewrite the address on every
 * order already placed against it the next time somebody moved house.
 *
 * # The account behind it, when there is one
 *
 * {@see $user} is the id of the signed-in user at the moment of checkout, or
 * null for a guest. It is what lets a shop running `dry-accounts` show a person
 * their orders — one query against one column — without this package and that
 * one keeping two unrelated records of the same person.
 *
 * Rows stay one-per-checkout either way. A returning account gets a second row
 * linked to the same user, so the address history is preserved and the account
 * is what ties the orders together.
 *
 * ## Why the column holds an id and not a model
 *
 * dry's ORM would hydrate this into the user object through
 * `Model::$special_fields`, the way {@see Order} turns its `customer` column
 * into a `Customer`. It deliberately does not here, for two reasons. The
 * handler has to be a real class name, and the user class belongs to a package
 * this one does not depend on — so it would have to be registered at runtime
 * from config, making `$user` an `int` in a shop that has not configured it and
 * an object in one that has. A property whose *type* turns on whether a config
 * key is set is worse to read, and worse to type-check, than a method that
 * always answers the same way. And a project that has accounts has its user
 * class already imported; `User::load($customer->getUserId())` is a line it can
 * write, and it does not have to guess which of the two things it got back.
 *
 * @see \Tnt\Ecommerce\Contracts\UserResolverInterface
 *
 * @property int|null $id
 * @property int|null $user The signed-in user's id, or null for a guest.
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

    /**
     * The id of the account behind this customer, or null for a guest.
     *
     * @return int|null
     */
    public function getUserId(): ?int
    {
        $user = $this->user;

        return $user === null ? null : (int) $user;
    }

    /**
     * Point this customer at an account.
     *
     * @param int|null $userId
     * @return void
     */
    public function setUserId(?int $userId): void
    {
        $this->user = $userId;
    }

    /**
     * Attach the account a checkout is being made from, and keep it.
     *
     * The single code path behind both kinds of checkout, called by
     * {@see \Tnt\Ecommerce\Cart\Cart::checkout()} with whatever
     * {@see \Tnt\Ecommerce\Contracts\UserResolverInterface} answered. A guest
     * checkout passes null, which is not an error and not a special case — it
     * is the same call, doing nothing, and the order that follows carries this
     * customer exactly as it would have anyway.
     *
     * Saves, because the row is written before the order that references it and
     * the link has to be on it by then. Saves *only* when there is something to
     * write, so a guest checkout costs no extra query.
     *
     * An already-linked row is left alone. A customer row belongs to the
     * checkout that created it, and re-pointing one at a different account
     * would rewrite who placed an order that has already been placed — so if
     * this is ever reached twice, the first answer is the one that stands.
     *
     * @param int|null $userId The signed-in user's id, or null for a guest.
     * @return void
     */
    public function linkTo(?int $userId): void
    {
        if ($userId === null || $this->getUserId() !== null) {
            return;
        }

        $this->setUserId($userId);
        $this->save();
    }
}
