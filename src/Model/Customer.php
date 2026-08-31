<?php

namespace Tnt\Ecommerce\Model;

use dry\db\DuplicateEntryException;
use dry\orm\Model;
use dry\orm\relationship\HasMany;
use Tnt\Ecommerce\Address\AddressType;
use Tnt\Ecommerce\AmbiguousAddress;
use Tnt\Ecommerce\Contracts\AddressInterface;
use Tnt\Ecommerce\Contracts\CustomerInterface;
use Tnt\Ecommerce\Contracts\HasAddressesInterface;
use Tnt\Ecommerce\Repository\CustomerRepository;

/**
 * The person an order was placed by, as stored in `ecommerce_customer`: one
 * row per account — schema-enforced by the UNIQUE on `user`; {@see forUser()}
 * is the lookup — a fresh row per guest, never matched by email, which is not
 * an identity claim. Keeps the address book ({@see Address}); the order
 * takes its own copy of everything an invoice prints. See docs/customer.md.
 *
 * @see \Tnt\Ecommerce\Contracts\UserResolverInterface
 *
 * @phpstan-consistent-constructor
 *
 * @property int|null $id
 * @property int|null $user The signed-in user's id, or null for a guest.
 * @property int $created
 * @property int $updated
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $company
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
     * Deliberately not a column: it lives exactly as long as the request.
     *
     * @var array<string, AddressInterface>
     * @see useAddress()
     */
    private array $chosen = [];

    /**
     * The one row on this account, creating it if this is the account's first
     * brush with the shop. Race-safe find-or-create: losing the insert race
     * throws into the UNIQUE index on `user` and re-reads the winner — the
     * same fingerprint pattern as ADN's ProductConfiguration. The email seed
     * keeps the package id-only: the shop passes what it knows, and only a
     * fresh row takes it — an existing row's email is not touched here (for
     * account customers the account owns it; see {@see \Tnt\Ecommerce\Account\SyncsCustomer}).
     * See docs/customer.md.
     *
     * @param int $userId
     * @param string $email Seed for a freshly created row only.
     * @return self
     */
    public static function forUser(int $userId, string $email = ''): self
    {
        $existing = static::findByUser($userId);

        if ($existing !== null) {
            return $existing;
        }

        $customer = new static();
        $customer->created = time();
        $customer->updated = time();
        $customer->user = $userId;
        $customer->email = $email;
        $customer->first_name = '';
        $customer->last_name = '';
        $customer->company = '';
        $customer->vat = '';
        $customer->comments = '';
        $customer->first_contact = '';

        try {
            $customer->save();
        } catch (DuplicateEntryException $lost) {
            // Another request minted the row between the find and the save.
            // The unique index is the lock; the winner's row is the answer.
            return static::findByUser($userId) ?? throw $lost;
        }

        return $customer;
    }

    /**
     * The row already on an account, or null — {@see forUser()}'s find, split
     * out so a test can run the race without a connection.
     *
     * @param int $userId
     * @return self|null
     */
    protected static function findByUser(int $userId): ?self
    {
        return CustomerRepository::create()->byUser($userId)->firstOrNull();
    }

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
     * @return string
     */
    public function getEmail(): string
    {
        return (string) $this->email;
    }

    /**
     * The company the account is in the name of, or ''.
     *
     * @return string
     */
    public function getCompanyName(): string
    {
        return (string) $this->company;
    }

    /**
     * The customer's VAT number, or '' when they have none.
     *
     * @return string
     */
    public function getVatNumber(): string
    {
        return (string) $this->vat;
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
     * The address book, as something a caller can loop over. Empty for an
     * unsaved customer, rather than a query against `WHERE customer = NULL`.
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
     * Use this address for the checkout about to happen — overrides what
     * {@see getAddress()} would have chosen, for this request only; nothing is
     * written. One per type, last call wins.
     *
     * @param AddressInterface $address
     * @return void
     */
    public function useAddress(AddressInterface $address): void
    {
        $this->chosen[$address->getType()->value] = $address;
    }

    /**
     * The address of a kind this customer's next checkout should use: the one
     * named with {@see useAddress()}, else the marked default, else the only
     * one of that kind. Null when the book holds none. Rather than guess,
     * several unmarked (or several marked) raises {@see AmbiguousAddress}.
     *
     * @param AddressType $type
     * @return AddressInterface|null
     *
     * @throws AmbiguousAddress If the book cannot say which one.
     */
    public function getAddress(AddressType $type): ?AddressInterface
    {
        if (isset($this->chosen[$type->value])) {
            return $this->chosen[$type->value];
        }

        $ofType = [];
        $defaults = [];

        foreach ($this->getAddresses() as $address) {
            if ($address->getType() !== $type) {
                continue;
            }

            $ofType[] = $address;

            if ($address->isDefault()) {
                $defaults[] = $address;
            }
        }

        if (count($defaults) > 1) {
            throw new AmbiguousAddress($type, count($ofType), true);
        }

        if (count($defaults) === 1) {
            return $defaults[0];
        }

        if (count($ofType) > 1) {
            throw new AmbiguousAddress($type, count($ofType));
        }

        return $ofType[0] ?? null;
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
     * Attach the account a checkout is being made from, and save. Null (a
     * guest) is a no-op, and an already-linked row is left alone — re-pointing
     * it would rewrite who placed an order already placed.
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
