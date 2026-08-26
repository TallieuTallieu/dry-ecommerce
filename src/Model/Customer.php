<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use dry\orm\relationship\HasMany;
use Tnt\Ecommerce\Address\AddressType;
use Tnt\Ecommerce\AmbiguousAddress;
use Tnt\Ecommerce\Contracts\AddressInterface;
use Tnt\Ecommerce\Contracts\CustomerInterface;
use Tnt\Ecommerce\Contracts\HasAddressesInterface;

/**
 * The person an order was placed by, as stored in `ecommerce_customer`: one
 * row per account, a fresh row per guest — never matched by email, which is
 * not an identity claim. Keeps the address book ({@see Address}); the order
 * takes its own copy of everything an invoice prints. See docs/customer.md.
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
