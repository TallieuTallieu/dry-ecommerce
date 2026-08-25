<?php

declare(strict_types=1);

/*
 * The order's own copy of who placed it and where it went.
 *
 * This is the ticket. Extracting an Address model out of twelve inline columns
 * is the visible half of sc-11172; the half that fixes a bug is that the order
 * stops reading through to the customer's address book and takes a copy
 * instead. Before it did, a customer who moved house and corrected the address
 * on file silently rewrote where every order they had ever placed was
 * delivered — and nothing anywhere said so out loud, because the order and the
 * address book were the same row.
 *
 * The tests below edit the book after checkout and read the order back. They
 * run at unit speed with no database, because Cart::newOrder() is a seam
 * (sc-11203) and Tests\Support\CustomerWithAddresses puts the address book in
 * an array — so the real Cart::checkout(), the real Order::freezeCustomer() and
 * the real Customer::getAddress() all run, against no connection.
 */

use Tests\Support\CustomerWithAddresses;
use Tests\Support\FakeBuyable;
use Tests\Support\UnsavedCustomer;
use Tnt\Ecommerce\Address\AddressType;
use Tnt\Ecommerce\Address\FrozenAddress;
use Tnt\Ecommerce\Model\Address;

beforeEach(function (): void {
    // checkout() dispatches Created through a facade, which wants a container.
    // Same three lines as CheckoutTest, and no connection between them.
    $app = new Oak\Container\Container();

    Oak\Facade::setContainer($app);

    $app->singleton(
        Oak\Contracts\Dispatcher\DispatcherInterface::class,
        Oak\Dispatcher\Dispatcher::class
    );
});

/**
 * An address book entry, built in memory.
 *
 * @param AddressType $type
 * @param array<string, string> $fields
 * @param int $created
 * @return Address
 */
function anAddress(
    AddressType $type,
    array $fields = [],
    int $created = 1000
): Address {
    $address = new Address();
    $address->setType($type);
    $address->created = $created;

    $address->first_name = $fields['first_name'] ?? 'Ada';
    $address->last_name = $fields['last_name'] ?? 'Lovelace';
    $address->street = $fields['street'] ?? 'Kortrijksesteenweg';
    $address->number = $fields['number'] ?? '1144';
    $address->postal_code = $fields['postal_code'] ?? '9051';
    $address->city = $fields['city'] ?? 'Gent';
    $address->country = $fields['country'] ?? 'BE';

    return $address;
}

/**
 * A customer with one address of each kind in their book.
 *
 * @return array{CustomerWithAddresses, Address, Address}
 */
function aCustomerWithABook(): array
{
    $customer = new CustomerWithAddresses();
    $customer->first_name = 'Ada';
    $customer->last_name = 'Lovelace';
    $customer->email = 'ada@example.com';

    $billing = anAddress(AddressType::Billing, [
        'street' => 'Gasmeterlaan',
        'number' => '103',
        'postal_code' => '9000',
        'city' => 'Gent',
    ]);

    $shipping = anAddress(AddressType::Shipping);

    $customer->keep($billing);
    $customer->keep($shipping);

    return [$customer, $billing, $shipping];
}

it('freezes both addresses onto the order at checkout', function (): void {
    [$cart] = makeCheckoutCart();
    [$customer] = aCustomerWithABook();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout($customer);

    $order = $cart->placed();

    // Every field of both addresses, so a column wired to the wrong getter
    // cannot pass. The two addresses differ in street, number and postcode on
    // purpose: a billing/shipping transposition fails here.
    expect($order->billing_first_name)->toBe('Ada');
    expect($order->billing_last_name)->toBe('Lovelace');
    expect($order->billing_street)->toBe('Gasmeterlaan');
    expect($order->billing_number)->toBe('103');
    expect($order->billing_postal_code)->toBe('9000');
    expect($order->billing_city)->toBe('Gent');
    expect($order->billing_country)->toBe('BE');

    expect($order->shipping_street)->toBe('Kortrijksesteenweg');
    expect($order->shipping_number)->toBe('1144');
    expect($order->shipping_postal_code)->toBe('9051');
    expect($order->shipping_city)->toBe('Gent');
    expect($order->shipping_country)->toBe('BE');
});

it('freezes the identity the order was placed with', function (): void {
    [$cart] = makeCheckoutCart();
    [$customer] = aCustomerWithABook();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout($customer);

    expect($cart->placed()->getFirstName())->toBe('Ada');
    expect($cart->placed()->getLastName())->toBe('Lovelace');
    expect($cart->placed()->getEmail())->toBe('ada@example.com');
});

it('leaves a placed order alone when an address is edited', function (): void {
    [$cart] = makeCheckoutCart();
    [$customer, , $shipping] = aCustomerWithABook();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout($customer);

    $order = $cart->placed();

    // A year later, the customer moves house and corrects their address book.
    $shipping->street = 'Nieuwstraat';
    $shipping->number = '12';
    $shipping->postal_code = '8000';
    $shipping->city = 'Brugge';

    // The book now says one thing and the order still says another, which is
    // the entire point: the parcel went to Gent, and no amount of editing can
    // make last year's invoice claim otherwise.
    expect($customer->getAddress(AddressType::Shipping)?->getStreet())->toBe(
        'Nieuwstraat'
    );

    expect($order->shipping_street)->toBe('Kortrijksesteenweg');
    expect($order->shipping_number)->toBe('1144');
    expect($order->shipping_postal_code)->toBe('9051');
    expect($order->shipping_city)->toBe('Gent');
});

it('leaves a placed order alone when an address is deleted', function (): void {
    [$cart] = makeCheckoutCart();
    [$customer, $billing, $shipping] = aCustomerWithABook();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout($customer);

    $order = $cart->placed();

    // The other half of "the book is mutable": a customer removes an address
    // they used once. A foreign key would leave the order pointing at nothing.
    $customer->forget($shipping);
    $customer->forget($billing);

    expect($customer->getAddress(AddressType::Shipping))->toBeNull();
    expect($customer->getAddress(AddressType::Billing))->toBeNull();

    expect($order->shipping_street)->toBe('Kortrijksesteenweg');
    expect($order->billing_street)->toBe('Gasmeterlaan');
    expect($order->getShippingAddress()->getCity())->toBe('Gent');
});

it('holds text and not the address it copied', function (): void {
    [$cart] = makeCheckoutCart();
    [$customer, , $shipping] = aCustomerWithABook();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout($customer);

    $frozen = $cart->placed()->getShippingAddress();

    // What every test above rests on, said directly. The order reads back an
    // address, but it is not *that* address — it is a FrozenAddress built from
    // the order's own columns, with nothing behind it to go and re-read.
    expect($frozen)->toBeInstanceOf(FrozenAddress::class);
    expect($frozen)->not->toBe($shipping);
    expect($cart->placed()->shipping_street)->toBeString();
    expect($frozen->getType())->toBe(AddressType::Shipping);
});

it('substitutes nothing for an address that was not given', function (): void {
    // The decision this ticket had to make and could get either way: a
    // customer who gave one address does not get it copied into the other
    // role. An order carrying a billing address the customer never gave for
    // billing is a worse record than one that admits none was given -- and a
    // shop that means "bill me where you ship" says so by keeping an address
    // of each kind, which is what the book is now able to express.
    [$cart] = makeCheckoutCart();

    $customer = new CustomerWithAddresses();
    $customer->first_name = 'Ada';
    $customer->last_name = 'Lovelace';
    $customer->email = 'ada@example.com';
    $customer->keep(anAddress(AddressType::Shipping));

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout($customer);

    $order = $cart->placed();

    expect($order->shipping_street)->toBe('Kortrijksesteenweg');
    expect($order->billing_street)->toBe('');
    expect($order->billing_city)->toBe('');

    $billing = $order->getBillingAddress();
    assert($billing instanceof FrozenAddress);

    expect($billing->isEmpty())->toBeTrue();
});

it('checks out a customer that keeps no addresses at all', function (): void {
    // A shop selling downloads, or one with its own customer class that does
    // not implement HasAddressesInterface. It is asked nothing, both sides
    // freeze blank, and the checkout is otherwise the same one.
    [$cart] = makeCheckoutCart();
    $customer = new UnsavedCustomer();
    $customer->first_name = 'Ada';
    $customer->last_name = 'Lovelace';
    $customer->email = 'ada@example.com';

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout($customer);

    $order = $cart->placed();

    expect($order->total)->toBe(2000);
    expect($order->getEmail())->toBe('ada@example.com');
    expect($order->shipping_street)->toBe('');
    expect($order->billing_street)->toBe('');
});

it('freezes the address the shop said to use', function (): void {
    // Which of several addresses of a kind a checkout uses is the shop's
    // decision, not this package's. useAddress() is how it says so, and it
    // beats the most-recent default.
    [$cart] = makeCheckoutCart();
    [$customer] = aCustomerWithABook();

    $office = anAddress(
        AddressType::Shipping,
        [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'street' => 'Bellevue',
            'number' => '5',
            'postal_code' => '9050',
            'city' => 'Ledeberg',
        ],
        500
    );

    $customer->keep($office);
    $customer->useAddress($office);

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout($customer);

    // Older than the one in the book, and chosen anyway.
    expect($cart->placed()->shipping_street)->toBe('Bellevue');
    expect($cart->placed()->shipping_city)->toBe('Ledeberg');
});
