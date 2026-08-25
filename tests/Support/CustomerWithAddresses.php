<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Model\Address;
use Tnt\Ecommerce\Model\Customer;

/**
 * A real customer whose address book is an array.
 *
 * {@see Customer::getAddresses()} is the one place the address book reaches for
 * a connection, so overriding it — and `save()`, as {@see UnsavedCustomer}
 * does — leaves the whole of {@see Customer::getAddress()} running as itself:
 * the "most recently added of this kind" rule, the type filter, the null when
 * there is none, and the override {@see Customer::useAddress()} applies on top.
 *
 * The array is what makes deletion testable at all. An `Address` object cannot
 * be made to vanish in PHP, but a book can stop containing it, and that is
 * precisely what deleting the row means to everything that reads the book. See
 * {@see forget()}.
 */
final class CustomerWithAddresses extends Customer
{
    /**
     * The book, newest last.
     *
     * @var array<int, Address>
     */
    private array $book = [];

    /**
     * How many times `save()` has been called.
     *
     * @var int
     */
    public int $saves = 0;

    /**
     * @return void
     */
    public function save()
    {
        $this->saves++;
    }

    /**
     * @return iterable<int, Address>
     */
    public function getAddresses(): iterable
    {
        return $this->book;
    }

    /**
     * Put an address in the book.
     *
     * @param Address $address
     * @return void
     */
    public function keep(Address $address): void
    {
        $this->book[] = $address;
    }

    /**
     * Take an address out of the book, as deleting the row would.
     *
     * @param Address $address
     * @return void
     */
    public function forget(Address $address): void
    {
        $this->book = array_values(
            array_filter(
                $this->book,
                static fn(Address $kept): bool => $kept !== $address
            )
        );
    }
}
