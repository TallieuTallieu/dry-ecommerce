<?php

declare(strict_types=1);

/*
 * The address book, and the enum that names its two halves.
 *
 * The behaviour that matters most — that an order stops reading through this
 * book once it is placed — is in tests/Feature/OrderAddressSnapshotTest.php.
 * What is here is the parts it is built from: what an address type knows about
 * itself, and how a customer picks one address out of a list of many.
 */

use Tests\Support\CustomerWithAddresses;
use Tnt\Ecommerce\Address\AddressType;
use Tnt\Ecommerce\Address\FrozenAddress;
use Tnt\Ecommerce\Model\Address;
use Tnt\Ecommerce\NotAnAddressType;

/**
 * @param AddressType $type
 * @param int $created
 * @param string $street
 * @return Address
 */
function bookEntry(
    AddressType $type,
    int $created,
    string $street = 'Nieuwstraat'
): Address {
    $address = new Address();
    $address->setType($type);
    $address->created = $created;
    $address->street = $street;

    return $address;
}

it('has a case for each question an order asks', function (): void {
    // Two, and no more. Where the invoice goes and where the parcel goes are
    // the only two things this package reads an address type for; a "home" or
    // "work" label is a shop's own vocabulary for its customers' addresses and
    // means nothing to a checkout.
    expect(AddressType::cases())->toBe([
        AddressType::Billing,
        AddressType::Shipping,
    ]);
});

it('names the order columns it freezes into', function (
    AddressType $type,
    string $prefix
): void {
    expect($type->columns())->toBe([
        $prefix . 'first_name',
        $prefix . 'last_name',
        $prefix . 'street',
        $prefix . 'number',
        $prefix . 'postal_code',
        $prefix . 'city',
        $prefix . 'country',
    ]);
})->with([
    [AddressType::Billing, 'billing_'],
    [AddressType::Shipping, 'shipping_'],
]);

it('copies every field of an address into its columns', function (): void {
    $address = new Address();
    $address->setType(AddressType::Shipping);
    $address->first_name = 'Ada';
    $address->last_name = 'Lovelace';
    $address->street = 'Kortrijksesteenweg';
    $address->number = '1144';
    $address->postal_code = '9051';
    $address->city = 'Gent';
    $address->country = 'BE';

    expect(AddressType::Shipping->snapshotOf($address))->toBe([
        'shipping_first_name' => 'Ada',
        'shipping_last_name' => 'Lovelace',
        'shipping_street' => 'Kortrijksesteenweg',
        'shipping_number' => '1144',
        'shipping_postal_code' => '9051',
        'shipping_city' => 'Gent',
        'shipping_country' => 'BE',
    ]);
});

it('reads a missing address as blank and not as null', function (): void {
    // Seven empty strings rather than seven nulls. The columns are NOT NULL
    // varchars, and a caller printing an address should not have to tell an
    // absent field from an empty one on every line.
    expect(AddressType::Billing->snapshotOf(null))->toBe([
        'billing_first_name' => '',
        'billing_last_name' => '',
        'billing_street' => '',
        'billing_number' => '',
        'billing_postal_code' => '',
        'billing_city' => '',
        'billing_country' => '',
    ]);
});

it('refuses a type it cannot read', function (): void {
    // Not defaulted to billing. Which kind an address is decides whether it
    // goes on an invoice or on a parcel, and a guess is wrong half the time in
    // a way nobody sees until the parcel arrives somewhere else.
    $address = new Address();
    $address->type = 'delivery';

    expect(fn() => $address->getType())->toThrow(NotAnAddressType::class);

    try {
        $address->getType();
    } catch (NotAnAddressType $refused) {
        expect($refused->getText())->toBe('delivery');
        expect($refused->getMessage())->toContain('billing or shipping');
    }
});

it('has no address book before it has been saved', function (): void {
    // The book is the set of rows pointing back at this one, so a customer
    // with no id has none. Answering that here is what keeps a customer built
    // in memory from running a query against `WHERE customer = NULL`.
    $customer = new Tnt\Ecommerce\Model\Customer();

    expect($customer->getAddresses())->toBe([]);
    expect($customer->getAddress(AddressType::Billing))->toBeNull();
});

it('picks the most recent address of the kind asked for', function (): void {
    $customer = new CustomerWithAddresses();
    $customer->keep(bookEntry(AddressType::Shipping, 100, 'Oude straat'));
    $customer->keep(bookEntry(AddressType::Billing, 300, 'Gasmeterlaan'));
    $customer->keep(bookEntry(AddressType::Shipping, 200, 'Nieuwe straat'));

    // Newest of that kind, not newest overall, and not first in the book.
    expect($customer->getAddress(AddressType::Shipping)?->getStreet())->toBe(
        'Nieuwe straat'
    );
    expect($customer->getAddress(AddressType::Billing)?->getStreet())->toBe(
        'Gasmeterlaan'
    );
});

it('answers null for a kind the book has none of', function (): void {
    $customer = new CustomerWithAddresses();
    $customer->keep(bookEntry(AddressType::Shipping, 100));

    // Not the shipping address under another name. See
    // Order::freezeCustomer() for why nothing is substituted.
    expect($customer->getAddress(AddressType::Billing))->toBeNull();
});

it('lets the shop override which address is used', function (): void {
    $customer = new CustomerWithAddresses();
    $customer->keep(bookEntry(AddressType::Shipping, 900, 'Newest'));

    $chosen = bookEntry(AddressType::Shipping, 100, 'Chosen');
    $customer->keep($chosen);
    $customer->useAddress($chosen);

    expect($customer->getAddress(AddressType::Shipping))->toBe($chosen);

    // One per kind: saying it twice is the shop changing its mind.
    $second = bookEntry(AddressType::Shipping, 50, 'Second thoughts');
    $customer->useAddress($second);

    expect($customer->getAddress(AddressType::Shipping))->toBe($second);

    // And a choice about one kind says nothing about the other.
    expect($customer->getAddress(AddressType::Billing))->toBeNull();
});

it('describes a frozen address without reaching for a row', function (): void {
    $frozen = new FrozenAddress(
        AddressType::Billing,
        'Ada',
        'Lovelace',
        'Gasmeterlaan',
        '103',
        '9000',
        'Gent',
        'BE'
    );

    expect($frozen->getType())->toBe(AddressType::Billing);
    expect($frozen->getFirstName())->toBe('Ada');
    expect($frozen->getLastName())->toBe('Lovelace');
    expect($frozen->getStreet())->toBe('Gasmeterlaan');
    expect($frozen->getNumber())->toBe('103');
    expect($frozen->getPostalCode())->toBe('9000');
    expect($frozen->getCity())->toBe('Gent');
    expect($frozen->getCountry())->toBe('BE');
    expect($frozen->isEmpty())->toBeFalse();
});

it('knows when an order recorded no address of a kind', function (): void {
    $frozen = new FrozenAddress(
        AddressType::Shipping,
        '',
        '',
        '',
        '',
        '',
        '',
        ''
    );

    expect($frozen->isEmpty())->toBeTrue();

    // One field is enough to make it a real address: an order that recorded
    // only a country still recorded something.
    $partial = new FrozenAddress(
        AddressType::Shipping,
        '',
        '',
        '',
        '',
        '',
        '',
        'BE'
    );

    expect($partial->isEmpty())->toBeFalse();
});

it('reads an address back as one line', function (): void {
    $address = new Address();
    $address->setType(AddressType::Shipping);
    $address->first_name = 'Ada';
    $address->last_name = 'Lovelace';
    $address->street = 'Kortrijksesteenweg';
    $address->number = '1144';
    $address->postal_code = '9051';
    $address->city = 'Gent';
    $address->country = 'BE';

    expect((string) $address)->toBe(
        'Ada Lovelace, Kortrijksesteenweg 1144, 9051 Gent, BE'
    );
});
