<?php

declare(strict_types=1);

/*
 * The account a customer row belongs to, and the rule for attaching one.
 *
 * Customer::linkTo() is the single code path behind both kinds of checkout.
 * Guest checkout passes null and account checkout passes an id; there is no
 * branch anywhere above it, and the first test below is that assertion made
 * against both values at once.
 *
 * Everything here runs with the database container stopped. `new Customer()`
 * needs no connection — dry's Model is an array wrapper — so the only step that
 * would is `save()`, which Tests\Support\UnsavedCustomer counts instead of
 * performing.
 */

use Tests\Support\FakeUserResolver;
use Tests\Support\UnsavedCustomer;
use Tnt\Ecommerce\Account\GuestUserResolver;
use Tnt\Ecommerce\Contracts\UserResolverInterface;
use Tnt\Ecommerce\Model\Customer;

it('records whoever was signed in, including nobody', function (
    ?int $signedIn
): void {
    $customer = new UnsavedCustomer();

    $customer->linkTo($signedIn);

    // The same call, the same absence of a branch, and an answer that is simply
    // whatever it was told. A guest is not an error state or a fallback; it is
    // a customer whose account id is null.
    expect($customer->getUserId())->toBe($signedIn);
})->with([
    'a guest checkout' => [null],
    'an account checkout' => [42],
]);

it('leaves a guest checkout unlinked', function (): void {
    $customer = new UnsavedCustomer();

    $customer->linkTo(null);

    expect($customer->getUserId())->toBeNull();

    // Nothing to write, so nothing is written. A shop with no accounts runs
    // every one of its checkouts through this line and pays no query for it.
    expect($customer->saves)->toBe(0);
});

it('links an account checkout to the signed-in user', function (): void {
    $customer = new UnsavedCustomer();

    $customer->linkTo(42);

    expect($customer->getUserId())->toBe(42);

    // Saved here rather than left to the caller, because the order that
    // references this row is written next and the link has to be on it by then.
    expect($customer->saves)->toBe(1);
});

it('keeps the account a row was already linked to', function (): void {
    $customer = new UnsavedCustomer();
    $customer->linkTo(42);

    // Re-pointing a customer row at a different account would rewrite who
    // placed an order that has already been placed. The row belongs to the
    // checkout that made it, so the first answer stands and the second write
    // never happens.
    $customer->linkTo(99);

    expect($customer->getUserId())->toBe(42);
    expect($customer->saves)->toBe(1);
});

it('reads an id back out of a stored row as an int', function (): void {
    // How a row actually arrives: Model::create() hands the driver's array
    // straight to the constructor, untouched and untyped, so whether the id
    // comes back as 42 or as '42' is the driver's business. getUserId() answers
    // int|null either way, because that is what the seam it is compared against
    // deals in.
    $customer = new UnsavedCustomer(['ecommerce_customer.user' => '42']);

    expect($customer->getUserId())->toBe(42);
});

it('reads a stored guest row back as no account', function (): void {
    $customer = new UnsavedCustomer(['ecommerce_customer.user' => null]);

    expect($customer->getUserId())->toBeNull();
});

it('starts every customer unlinked', function (): void {
    // A fresh row has no account until a checkout gives it one, which is also
    // what makes the guest path need no special casing.
    expect((new UnsavedCustomer())->getUserId())->toBeNull();
});

/*
 * The seam that answers the question.
 */

it('answers nobody when a shop has no accounts', function (): void {
    $resolver = new GuestUserResolver();

    expect($resolver)->toBeInstanceOf(UserResolverInterface::class);
    expect($resolver->getCurrentUserId())->toBeNull();
});

it('links a customer to whatever the resolver reports', function (
    ?int $signedIn
): void {
    // The two halves joined up: a resolver answers, and the row records it.
    // This is what Cart::checkout() does, in the one line it takes to do it.
    $customer = new UnsavedCustomer();
    $resolver = new FakeUserResolver($signedIn);

    $customer->linkTo($resolver->getCurrentUserId());

    expect($customer->getUserId())->toBe($signedIn);
})->with([
    'a guest checkout' => [null],
    'an account checkout' => [7],
]);

it('never turns an email address into an identity claim', function (): void {
    // The deliberate non-feature. Two guests at the same address are two
    // unrelated rows, because deduplicating them would let anyone check out as
    // somebody else's email and merge into their record. An account is a claim
    // that has been authenticated; an address is not.
    $first = new UnsavedCustomer();
    $first->email = 'ada@example.com';
    $first->linkTo(null);

    $second = new UnsavedCustomer();
    $second->email = 'ada@example.com';
    $second->linkTo(null);

    expect($first->getUserId())->toBeNull();
    expect($second->getUserId())->toBeNull();
    expect($first)->not->toBe($second);
});

it('carries the link on the model an order points at', function (): void {
    // linkTo() lives on Customer rather than on the cart, so the account
    // travels with the row the order's foreign key already resolves to. This is
    // the alternative to a polymorphic customer with a customer_class column,
    // and it is why an order keeps a real foreign key.
    $customer = new UnsavedCustomer();
    $customer->linkTo(42);

    expect($customer)->toBeInstanceOf(Customer::class);
    expect($customer->getUserId())->toBe(42);
});
