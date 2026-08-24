<?php

declare(strict_types=1);

/*
 * What the tax comes to, under both conventions.
 *
 * Whether a price already contains its VAT is the one fact this package cannot
 * work out for itself, and everything here turns on it. A price of 1250 at 21%
 * is either €12.50 of which 217 is VAT, or €12.50 plus 263 of VAT — and the
 * two differ by the whole tax amount, not by a rounding.
 *
 * So a shop says which it means and Tnt\Ecommerce\Tax\PriceConvention holds the
 * answer. Under Inclusive the tax is reported and the total does not move;
 * under Exclusive it is charged and lands in the total. Both are ordinary, and
 * the tests below run the same carts through each so the difference is visible
 * rather than asserted twice in different words.
 *
 * Which lines are taxable at all is BuyableCapabilitiesTest's business. This
 * file assumes the capability works and asks what the figures are.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeCoupon;
use Tests\Support\FakeDiscountCode;
use Tests\Support\FakeFulfillment;
use Tests\Support\FakeTaxableBuyable;
use Tests\Support\PercentageTaxRate;
use Tnt\Ecommerce\Cart\Cart;
use Tnt\Ecommerce\Cart\InMemoryCartStorage;
use Tnt\Ecommerce\Shop\Shop;
use Tnt\Ecommerce\Tax\PriceConvention;
use Tnt\Ecommerce\Tax\TaxPolicy;

/**
 * A cart taxed under a named convention.
 *
 * @param PriceConvention $convention
 * @param int|float|null $deliveryRate
 * @param array<int, FakeFulfillment> $fulfillments
 * @return array{0: Cart, 1: InMemoryCartStorage, 2: Shop}
 */
function taxedCart(
    PriceConvention $convention,
    int|float|null $deliveryRate = null,
    array $fulfillments = []
): array {
    return makeCart(
        $fulfillments,
        null,
        new TaxPolicy($convention, $deliveryRate)
    );
}

it('reports the tax contained in an inclusive price', function (): void {
    [$cart] = taxedCart(PriceConvention::Inclusive);

    $cart->add(new FakeTaxableBuyable('1', 1250, new PercentageTaxRate(21)));

    // 1250 x 21/121. The customer pays 1250 either way; this says how much of
    // it was tax.
    expect($cart->getTax())->toBe(217);
    expect($cart->getSubTotal())->toBe(1250);
    expect($cart->getTotal())->toBe(1250);
});

it('adds the tax on top of an exclusive price', function (): void {
    [$cart] = taxedCart(PriceConvention::Exclusive);

    $cart->add(new FakeTaxableBuyable('1', 1250, new PercentageTaxRate(21)));

    // 1250 x 21/100, and the customer pays 1513.
    expect($cart->getTax())->toBe(263);
    expect($cart->getSubTotal())->toBe(1250);
    expect($cart->getTotal())->toBe(1513);
});

it('leaves an inclusive total exactly where it was', function (): void {
    // The reason Inclusive is the default. A shop that upgrades and configures
    // nothing must not find every total 21% higher than it was yesterday.
    [$before] = makeCart();
    [$after] = taxedCart(PriceConvention::Inclusive);

    $before->add(new FakeBuyable('1', 1250));
    $after->add(new FakeTaxableBuyable('1', 1250, new PercentageTaxRate(21)));

    expect($after->getTotal())->toBe($before->getTotal());
});

it('keeps the per-line rounding rule under both conventions', function (
    PriceConvention $convention,
    int $price,
    int $perLine,
    int $asOneLine
): void {
    // Two lines against one line of twice the price: the same money, a
    // different number of printed figures, so a different answer. A line is
    // the unit that gets printed and checked, so the total is the sum of the
    // lines -- the rule Tnt\Ecommerce\Money has documented since sc-11170.
    //
    // It holds whichever direction the rate is applied in, but the amounts
    // that show it differ. 1250 at 21% divides awkwardly on the way up and
    // evenly on the way in; 95 does the reverse. Each dataset uses a price
    // where its own convention actually rounds, because a pair of numbers that
    // happened to agree would assert nothing.
    [$two] = taxedCart($convention);
    $two->add(new FakeTaxableBuyable('1', $price, new PercentageTaxRate(21)));
    $two->add(new FakeTaxableBuyable('2', $price, new PercentageTaxRate(21)));

    [$one] = taxedCart($convention);
    $one->add(
        new FakeTaxableBuyable('1', $price, new PercentageTaxRate(21)),
        2
    );

    expect($two->getTax())->toBe($perLine);
    expect($one->getTax())->toBe($asOneLine);
    expect($perLine)->not->toBe($asOneLine);
})->with([
    // 95 x 21/121 = 16.49 -> 16, twice is 32; 190 in one go is 32.98 -> 33.
    'inclusive' => [PriceConvention::Inclusive, 95, 32, 33],
    // 1250 x 21/100 = 262.5 -> 263, twice is 526; 2500 in one go is 525.
    'exclusive' => [PriceConvention::Exclusive, 1250, 526, 525],
]);

it('taxes each line at its own rate', function (): void {
    [$cart] = taxedCart(PriceConvention::Exclusive);

    $cart->add(new FakeTaxableBuyable('1', 2000, new PercentageTaxRate(21)));
    $cart->add(new FakeTaxableBuyable('2', 500, new PercentageTaxRate(6)));

    expect($cart->getTax())->toBe(420 + 30);
    expect($cart->getTotal())->toBe(2500 + 450);
});

/*
 * The discount.
 */

it('taxes what is left of a line after the coupon', function (): void {
    [$cart] = taxedCart(PriceConvention::Exclusive);

    $cart->add(new FakeTaxableBuyable('1', 10_000, new PercentageTaxRate(21)));
    $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(1000)));

    // €10.00 off €100.00, so tax is charged on €90.00 and not on €100.00. The
    // difference is 210 cents the customer would otherwise be charged tax on
    // money they never paid.
    expect($cart->getReduction())->toBe(1000);
    expect($cart->getTax())->toBe(1890);
    expect($cart->getTotal())->toBe(10_000 - 1000 + 1890);
});

it('spreads the coupon across lines at different rates', function (): void {
    [$cart] = taxedCart(PriceConvention::Exclusive);

    $cart->add(new FakeTaxableBuyable('1', 2000, new PercentageTaxRate(21)));
    $cart->add(new FakeTaxableBuyable('2', 500, new PercentageTaxRate(6)));
    $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(250)));

    // 250 apportioned by line value: 200 off the 21% line and 50 off the 6%
    // one. Taking it all off either line would be cheaper to compute and would
    // charge the wrong tax, because the two lines are taxed at rates that
    // differ by a factor of three.
    expect($cart->getTax())->toBe(378 + 27);
});

it('spreads a coupon over untaxed lines too', function (): void {
    [$cart] = taxedCart(PriceConvention::Exclusive);

    $cart->add(new FakeTaxableBuyable('1', 1000, new PercentageTaxRate(21)));
    $cart->add(new FakeBuyable('2', 1000));
    $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(200)));

    // The discount is the cart's, so half of it belongs to the untaxed line
    // even though that line pays no tax. Charging the whole 200 against the
    // taxable line would tax it on 800 when the customer paid 900 for it.
    expect($cart->getTax())->toBe(189);
});

it('reduces contained tax by the coupon as well', function (): void {
    [$cart] = taxedCart(PriceConvention::Inclusive);

    $cart->add(new FakeTaxableBuyable('1', 12_100, new PercentageTaxRate(21)));
    $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(1210)));

    // A discount on a gross price discounts the tax inside it too: 10 890
    // still has 21% in it, and 21/121 of that is 1890.
    expect($cart->getTax())->toBe(1890);
    expect($cart->getTotal())->toBe(10_890);
});

/*
 * Delivery.
 */

it('taxes delivery at the configured rate', function (): void {
    $post = new FakeFulfillment('post', 475);
    [$cart] = taxedCart(PriceConvention::Exclusive, 21, [$post]);

    $cart->add(new FakeTaxableBuyable('1', 1000, new PercentageTaxRate(21)));
    $cart->setFulfillment($post);

    expect($cart->getTax())->toBe(210 + 100);
    expect($cart->getTotal())->toBe(1000 + 475 + 310);
});

it('leaves delivery untaxed when no rate is set', function (): void {
    $post = new FakeFulfillment('post', 475);
    [$cart] = taxedCart(PriceConvention::Exclusive, null, [$post]);

    $cart->add(new FakeTaxableBuyable('1', 1000, new PercentageTaxRate(21)));
    $cart->setFulfillment($post);

    // No rate configured means no figure invented for it. Different from a
    // configured rate of 0, which is a zero-rated supply and prints as one.
    expect($cart->getTax())->toBe(210);
});

it(
    'taxes delivery even when nothing in the cart is taxable',
    function (): void {
        $post = new FakeFulfillment('post', 475);
        [$cart] = taxedCart(PriceConvention::Exclusive, 21, [$post]);

        $cart->add(new FakeBuyable('1', 1000));
        $cart->setFulfillment($post);

        // Delivery is a supply in its own right and carries the shop's rate, which
        // is exactly the simplification chosen here: the rate does not follow what
        // is in the cart.
        expect($cart->getTax())->toBe(100);
    }
);

it('taxes no delivery when none was chosen', function (): void {
    [$cart] = taxedCart(PriceConvention::Exclusive, 21);

    $cart->add(new FakeTaxableBuyable('1', 1000, new PercentageTaxRate(21)));

    expect($cart->getFulfillmentCost())->toBe(0);
    expect($cart->getTax())->toBe(210);
});
