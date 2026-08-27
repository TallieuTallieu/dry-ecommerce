<?php

declare(strict_types=1);

/*
 * The cookie-backed cart: a token in a cookie of its own instead of a row id
 * in the session, so the basket survives the session dying.
 *
 * What runs here is the production CookieCartStorage minus its two database
 * lines (InMemoryCookieCartStorage replaces the token query and the row
 * insert; see tests/Support). Two storage instances over one CartTable are
 * two requests over one database, which is what makes the round trip — leave,
 * come back, basket intact — assertable without a browser.
 */

use Tests\Support\CartTable;
use Tests\Support\FakeCookie;
use Tests\Support\InMemoryCookieCartStorage;
use Tnt\Ecommerce\Cart\CookieCartStorage;

/**
 * A "shop" to run requests against: one table, one browser's cookie jar.
 *
 * @return array{CartTable, FakeCookie}
 */
function cookieCartShop(): array
{
    return [new CartTable(), new FakeCookie()];
}

/**
 * One request's storage over that shop.
 *
 * @param CartTable $table
 * @param FakeCookie $cookie
 * @param int $days
 * @return InMemoryCookieCartStorage
 */
function cookieCartRequest(
    CartTable $table,
    FakeCookie $cookie,
    int $days = 30
): InMemoryCookieCartStorage {
    return new InMemoryCookieCartStorage($cookie, $days, $table);
}

it(
    'mints a token at row creation and hands it to the cookie',
    function (): void {
        [$table, $cookie] = cookieCartShop();

        cookieCartRequest($table, $cookie)->setFulfillmentId('pickup');

        $token = $cookie->get(CookieCartStorage::COOKIE_NAME);

        // bin2hex(random_bytes(16)): 32 hex characters, and demonstrably not the
        // row id — the cookie never holds anything a visitor could count up.
        expect($token)->toMatch('/^[0-9a-f]{32}$/');
        expect($table->rows)->toHaveCount(1);
        expect($token)->not->toBe((string) array_key_first($table->rows));
    }
);

it('sets the cookie to live for the configured days', function (): void {
    [$table, $cookie] = cookieCartShop();

    cookieCartRequest($table, $cookie, 30)->setFulfillmentId('pickup');

    $expiry = $cookie->expiries[CookieCartStorage::COOKIE_NAME];

    expect($expiry)->toBeGreaterThanOrEqual(time() + 30 * 86400 - 5);
    expect($expiry)->toBeLessThanOrEqual(time() + 30 * 86400 + 5);
});

it('finds the same cart back on the next request', function (): void {
    [$table, $cookie] = cookieCartShop();

    $first = cookieCartRequest($table, $cookie);
    $first->setFulfillmentId('pickup');
    $first->setOrderId(12);

    // A fresh instance with nothing but the cookie: the session is gone, the
    // token is the whole memory.
    $second = cookieCartRequest($table, $cookie);

    expect($second->getFulfillmentId())->toBe('pickup');
    expect($second->getOrderId())->toBe(12);
});

it('reads a token pointing at nothing as no cart', function (): void {
    [$table, $cookie] = cookieCartShop();

    // A perfectly-shaped token whose row is gone — reaped, or invented.
    $cookie->set(CookieCartStorage::COOKIE_NAME, str_repeat('ab', 16));

    $storage = cookieCartRequest($table, $cookie);

    expect($storage->items())->toBe([]);
    expect($storage->getFulfillmentId())->toBeNull();
    expect($storage->getOrderId())->toBeNull();
});

it('reads a soft-deleted cart as no cart', function (): void {
    // The Paid listener's mark. The row is still there — provenance — but it
    // is nobody's cart any more, and the next visit starts fresh.
    [$table, $cookie] = cookieCartShop();

    $first = cookieCartRequest($table, $cookie);
    $first->setFulfillmentId('pickup');
    $first->setOrderId(12);

    $cart = $table->only();
    $cart->deleted = time();

    $second = cookieCartRequest($table, $cookie);

    expect($second->getFulfillmentId())->toBeNull();
    expect($second->getOrderId())->toBeNull();
    expect($second->items())->toBe([]);
});

it('refuses cookie values that are not tokens', function (mixed $value): void {
    // Whatever ends up in the cookie — a row id, garbage, an empty string —
    // is only ever compared against the token pattern, never queried on.
    [$table, $cookie] = cookieCartShop();

    $cookie->set(CookieCartStorage::COOKIE_NAME, $value);

    expect(cookieCartRequest($table, $cookie)->getFulfillmentId())->toBeNull();
})->with([
    'a row id' => ['7'],
    'the empty string a cleared cookie holds' => [''],
    'a truncated token' => ['abc123'],
    'uppercase hex' => [strtoupper(str_repeat('ab', 16))],
    'not a string' => [7],
]);

it('clears hard and expires the cookie', function (): void {
    // clear() keeps its 1.x meaning: the explicit call deletes the row. The
    // soft path — deleted-and-kept — belongs to the Paid listener alone.
    [$table, $cookie] = cookieCartShop();

    $first = cookieCartRequest($table, $cookie);
    $first->setFulfillmentId('pickup');

    $cart = $table->only();

    $first->clear();

    expect($cart->hardDeleted)->toBeTrue();
    expect($table->rows)->toBe([]);
    expect($cookie->expiries[CookieCartStorage::COOKIE_NAME])->toBeLessThan(
        time()
    );

    // And the next request starts empty rather than resurrecting anything.
    expect(cookieCartRequest($table, $cookie)->getFulfillmentId())->toBeNull();
});
