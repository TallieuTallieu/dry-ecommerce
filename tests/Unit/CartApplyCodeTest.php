<?php

declare(strict_types=1);

/*
 * A typed code through the cart: looked up in any case, put on the cart or
 * refused with what the message needs, the cart left as it was on a refusal.
 */

use Oak\Dispatcher\Dispatcher;
use Tests\Support\CodeLookupCart;
use Tests\Support\FakeBuyable;
use Tests\Support\FakeCoupon;
use Tests\Support\FakeDiscountCode;
use Tests\Support\FakePayment;
use Tests\Support\FakeRedirector;
use Tests\Support\InMemoryCoupon;
use Tests\Support\InMemoryPaymentLedger;
use Tnt\Ecommerce\Account\GuestUserResolver;
use Tnt\Ecommerce\Cart\InMemoryCartStorage;
use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Discount\CouponRefusal;
use Tnt\Ecommerce\Discount\RefusedCode;
use Tnt\Ecommerce\Fulfillment\InMemoryAttributeStorage;
use Tnt\Ecommerce\Model\DiscountCode;
use Tnt\Ecommerce\Shop\Shop;

/**
 * A cart holding 3000 that takes a discount, which knows these code rows.
 *
 * @param DiscountCode ...$codes
 * @return CodeLookupCart
 */
function cartKnowing(DiscountCode ...$codes): CodeLookupCart
{
    $storage = new InMemoryCartStorage();
    $cart = new CodeLookupCart(
        new Shop(new InMemoryAttributeStorage()),
        $storage,
        new FakePayment(),
        new GuestUserResolver(),
        new InMemoryPaymentLedger(new Dispatcher()),
        new FakeRedirector()
    );
    $cart->storedIn($storage);

    foreach ($codes as $code) {
        $cart->knowing($code);
    }

    $cart->add(new FakeBuyable('1', 3000));

    return $cart;
}

/**
 * A code row for the shipped coupon, under its own code.
 *
 * @param InMemoryCoupon $coupon
 * @param string $code
 * @return DiscountCode
 */
function codeFor(InMemoryCoupon $coupon, string $code): DiscountCode
{
    return FakeDiscountCode::forCoupon(
        $coupon->keptIn($coupon->store()),
        $code
    );
}

it('applies a code typed in any case, spaces around it', function (): void {
    $code = codeFor(InMemoryCoupon::percentage(10), 'SUMMER10');
    $cart = cartKnowing($code);

    expect($cart->applyCode('  summer10 '))->toBeNull();
    expect($cart->getDiscount())->toBe($code);
    expect($cart->getReduction())->toBe(300);
});

it('refuses an empty field without looking it up', function (
    string $typed
): void {
    $cart = cartKnowing();

    expect($cart->applyCode($typed))->toEqual(
        new RefusedCode(CouponRefusal::Missing)
    );
    expect($cart->lookups)->toBe(0);
    expect($cart->stored())->toBeNull();
})->with(['', '   ']);

it('refuses a code nobody holds, and one without a coupon', function (): void {
    $orphan = FakeDiscountCode::withoutCoupon();
    $orphan->code = 'ORPHAN';
    $cart = cartKnowing($orphan);

    expect($cart->applyCode('NOPE'))->toEqual(
        new RefusedCode(CouponRefusal::Unknown)
    );
    expect($cart->applyCode('orphan'))->toEqual(
        new RefusedCode(CouponRefusal::Unknown)
    );
    expect($cart->stored())->toBeNull();
});

it('says when a coupon that has not started starts', function (): void {
    $coupon = InMemoryCoupon::percentage(10);
    $coupon->starts = $coupon->clock + 86400;
    $cart = cartKnowing(codeFor($coupon, 'LATER'));

    expect($cart->applyCode('later'))->toEqual(
        new RefusedCode(
            CouponRefusal::NotStarted,
            starts: $coupon->clock + 86400
        )
    );
    expect($cart->stored())->toBeNull();
});

it('says what minimum a cart falls below', function (): void {
    $coupon = InMemoryCoupon::fixed(1000);
    $coupon->minimum = 5000;
    $cart = cartKnowing(codeFor($coupon, 'BIG'));

    expect($cart->applyCode('big'))->toEqual(
        new RefusedCode(CouponRefusal::BelowMinimum, minimum: 5000)
    );
    expect($cart->stored())->toBeNull();
});

it('carries no start or minimum the reason does not need', function (): void {
    $coupon = InMemoryCoupon::fixed(1000);
    $coupon->minimum = 1000;
    $coupon->balance = 0;
    $cart = cartKnowing(codeFor($coupon, 'SPENT'));

    expect($cart->applyCode('spent'))->toEqual(
        new RefusedCode(CouponRefusal::Empty)
    );
    expect($cart->stored())->toBeNull();
});

it('reads a shop\'s own coupon\'s no as unknown', function (): void {
    $code = FakeDiscountCode::withCoupon(
        new FakeCoupon(100, redeemable: false)
    );
    $code->code = 'OWN';
    $cart = cartKnowing($code);

    expect($cart->applyCode('own'))->toEqual(
        new RefusedCode(CouponRefusal::Unknown)
    );
    expect($cart->stored())->toBeNull();
});

it('keeps the applied code when another is refused', function (): void {
    $applied = codeFor(InMemoryCoupon::percentage(10), 'SUMMER10');
    $expired = InMemoryCoupon::percentage(50);
    $expired->ends = $expired->clock - 1;
    $cart = cartKnowing($applied, codeFor($expired, 'GONE'));

    $cart->applyCode('summer10');

    expect($cart->applyCode('gone')?->reason)->toBe(CouponRefusal::Expired);
    expect($cart->stored())->toBe($applied);
});

it('replaces the applied code with another that applies', function (): void {
    $first = codeFor(InMemoryCoupon::percentage(10), 'SUMMER10');
    $second = codeFor(InMemoryCoupon::fixed(500), 'GIFT');
    $cart = cartKnowing($first, $second);

    $cart->applyCode('summer10');

    expect($cart->applyCode('gift'))->toBeNull();
    expect($cart->getDiscount())->toBe($second);
    expect($cart->getReduction())->toBe(500);
});

it('changes nothing when the applied code is typed again', function (): void {
    $code = codeFor(InMemoryCoupon::percentage(10), 'SUMMER10');
    $cart = cartKnowing($code);
    $cart->applyCode('SUMMER10');

    expect($cart->applyCode(' summer10'))->toBeNull();
    expect($cart->lookups)->toBe(1);
    expect($cart->getDiscount())->toBe($code);
});

it('refuses the stored code again once it stopped applying', function (): void {
    $coupon = InMemoryCoupon::fixed(1000);
    $coupon->minimum = 3000;
    $code = codeFor($coupon, 'MIN30');
    $cart = cartKnowing($code);
    $cart->applyCode('min30');

    $cart->removeItem($cart->items()[0]->getId());
    $cart->add(new FakeBuyable('2', 2000));

    expect($cart->applyCode('min30'))->toEqual(
        new RefusedCode(CouponRefusal::BelowMinimum, minimum: 3000)
    );
    expect($cart->stored())->toBe($code);
});

it('applies and removes a code through the interface', function (): void {
    $through = static function (CartInterface $cart): void {
        expect($cart->applyCode('summer10'))->toBeNull();

        $cart->removeDiscount();

        expect($cart->getDiscount())->toBeNull();
        expect($cart->getReduction())->toBe(0);
    };

    $through(cartKnowing(codeFor(InMemoryCoupon::percentage(10), 'SUMMER10')));
});

it('asks RefusedCode::of() without applying', function (): void {
    $cart = cartKnowing();

    expect(
        RefusedCode::of(
            codeFor(InMemoryCoupon::percentage(10), 'SUMMER10'),
            $cart
        )
    )->toBeNull();
    expect(RefusedCode::of(null, $cart))->toEqual(
        new RefusedCode(CouponRefusal::Unknown)
    );
});
