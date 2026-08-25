<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use dry\orm\relationship\HasMany;
use Tnt\Ecommerce\Address\AddressType;
use Tnt\Ecommerce\Contracts\AddressInterface;
use Tnt\Ecommerce\Contracts\CustomerInterface;
use Tnt\Ecommerce\Contracts\HasAddressesInterface;

/**
 * The person an order was placed by, as stored in `ecommerce_customer`.
 *
 * # A row per checkout, not a row per person
 *
 * A customer row is a record of who placed one order. It is not a profile, and
 * nothing in this package looks one up to reuse it —
 * {@see \Tnt\Ecommerce\Repository\CustomerRepository::byEmail()} says why
 * matching on an email address would be wrong.
 *
 * # The address book
 *
 * The twelve inline `address_*` and `shipping_*` columns this row used to carry
 * are gone, replaced by a one-to-many into `ecommerce_address`
 * ({@see Address}). They gave a customer exactly one billing and one shipping
 * address for ever, which is not a shape an address *list* can be squeezed
 * into.
 *
 * Two things follow that used to be the same thing and no longer are. Editing
 * an address here is now an ordinary thing to allow, because no order reads
 * through this row to find out where it was sent — {@see Order} took its own
 * copy at checkout. And which address of a kind an order uses is now a choice,
 * because there can be several; see {@see getAddress()}.
 *
 * # The account behind it, when there is one
 *
 * {@see $user} is the id of the signed-in user at the moment of checkout, or
 * null for a guest. It is what lets a shop running `dry-accounts` show a person
 * their orders — one query against one column — without this package and that
 * one keeping two unrelated records of the same person.
 *
 * Rows stay one-per-checkout either way. A returning account gets a second row
 * linked to the same user, and the account is what ties the orders together.
 * That used to be load-bearing for a second reason — reusing a row would have
 * rewritten the address on every order already placed against it — and no
 * longer is, because an order stopped reading its address through this row at
 * all. See {@see Order::freezeCustomer()}.
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
 * @property string $vat
 * @property string $comments
 * @property string $first_contact
 * @property-read HasMany $addresses
 */
class Customer extends Model implements CustomerInterface, HasAddressesInterface
{
    const TABLE = 'ecommerce_customer';

    /**
     * The addresses the shop has picked for this checkout, by type.
     *
     * Deliberately not a column. It is a choice being made right now — the one
     * the customer clicked on the checkout page — and it lives exactly as long
     * as the request that makes it, because the moment the order is placed the
     * order has its own copy and this is of no further interest.
     *
     * @var array<string, AddressInterface>
     * @see useAddress()
     */
    private array $chosen = [];

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
        return (string) $this->first_name;
    }

    public function getLastName(): string
    {
        return (string) $this->last_name;
    }

    /**
     * The email address this checkout was made with.
     *
     * Cast rather than returned bare, because a customer built in memory and
     * not yet filled in has no value in the column and `null` is not a string.
     * The same goes for the two names above.
     *
     * @return string
     */
    public function getEmail(): string
    {
        return (string) $this->email;
    }

    /**
     * This customer's address book.
     *
     * @return HasMany
     */
    public function get_addresses()
    {
        return $this->has_many(Address::class, 'customer');
    }

    /**
     * The address book, as something a caller can loop over.
     *
     * Empty for a customer that has not been saved: the book is the set of rows
     * pointing back at this one, and a row with no id has nothing pointing at
     * it. Saying so here rather than letting the relationship run a query
     * against `WHERE customer = NULL` is what keeps a customer usable — and
     * testable — before it is written.
     *
     * @return iterable<int, Address>
     */
    public function getAddresses(): iterable
    {
        if ($this->id === null) {
            return [];
        }

        /** @var iterable<int, Address> */
        return $this->addresses;
    }

    /**
     * Use this address for the checkout about to happen.
     *
     * How a shop says which address the customer picked, when the book holds
     * more than one of a kind. Overrides what {@see getAddress()} would have
     * chosen, for this request only — nothing is written, because the order is
     * about to take its own copy and there is nothing here worth persisting.
     *
     * One per type: calling it twice with two shipping addresses is the shop
     * changing its mind, and the second one stands.
     *
     * @param AddressInterface $address
     * @return void
     */
    public function useAddress(AddressInterface $address): void
    {
        $this->chosen[$address->getType()->value] = $address;
    }

    /**
     * The address an order of this kind would be frozen with, or null.
     *
     * Whatever {@see useAddress()} was told, when the shop has told it
     * anything. Otherwise the most recently added address of that kind, which
     * is a *default* and not a rule: a package cannot know whether this shop
     * lets people choose at checkout, so it picks the one answer that is right
     * for the shop that never asks — the address the customer most recently
     * said was theirs — and gets out of the way of the shop that does ask.
     *
     * Null when the book holds none of that kind. That is an ordinary answer,
     * not a failure, and nothing is substituted from the other kind; see
     * {@see Order::freezeCustomer()}.
     *
     * @param AddressType $type
     * @return AddressInterface|null
     */
    public function getAddress(AddressType $type): ?AddressInterface
    {
        if (isset($this->chosen[$type->value])) {
            return $this->chosen[$type->value];
        }

        $latest = null;

        foreach ($this->getAddresses() as $address) {
            if ($address->getType() !== $type) {
                continue;
            }

            if ($latest === null || $address->created >= $latest->created) {
                $latest = $address;
            }
        }

        return $latest;
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
