<?php

declare(strict_types=1);

/*
 * Stock and tax as things a buyable opts into, rather than things it owes.
 *
 * BuyableInterface used to demand a stock worker and a tax rate from everything
 * for sale. Nothing that has neither could satisfy that honestly, so the package
 * shipped a NullStockWorker and a NullTaxRate for them to hand back — two
 * classes whose whole job was to make a contract's questions go away. Both are
 * gone, and the questions are asked only of the buyables that offer to answer
 * them: HasStockInterface and TaxableInterface.
 *
 * The first test below is the one that matters most, because it is the same
 * assertion made against all four combinations of the two capabilities. What it
 * pins down is that they are genuinely independent — a buyable is counted or
 * not, taxed or not, and neither choice leaks into the other or into the
 * arithmetic in CartTest.
 *
 * Amounts are integer cents throughout, and stock quantities are whole. Tax is
 * reported by Cart::getTax() and deliberately does not enter Cart::getTotal();
 * the last test in the tax group holds that line, and TaxableInterface says why
 * it is drawn there.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeDiscountCode;
use Tests\Support\FakeStockedBuyable;
use Tests\Support\FakeStockedTaxableBuyable;
use Tests\Support\FakeStockWorker;
use Tests\Support\FakeTaxableBuyable;
use Tests\Support\PercentageCoupon;
use Tests\Support\PercentageTaxRate;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\HasStockInterface;
use Tnt\Ecommerce\Contracts\TaxableInterface;

it(
    'applies stock and tax only where the buyable asks for them',
    /**
     * @param Closure(FakeStockWorker): BuyableInterface $make
     * @param bool $counted Whether canAdd() should consult the stock at all.
     * @param bool $taxed Whether the line should contribute to the cart's tax.
     */
    function (Closure $make, bool $counted, bool $taxed): void {
        $stock = new FakeStockWorker();
        $buyable = $make($stock);
        $stock->increment($buyable, 1);

        [$cart] = makeCart();

        // Exactly one is in stock, so a counted buyable reports that two are
        // not there; an uncounted one never asks and reports yes to either.
        expect($cart->canAdd($buyable))->toBeTrue();
        expect($cart->canAdd($buyable, 2))->toBe(!$counted);

        $cart->add($buyable);

        expect($cart->items())->toHaveCount(1);
        expect($cart->getSubTotal())->toBe(1000);

        // 21% of €10.00.
        expect($cart->getTax())->toBe($taxed ? 210 : 0);

        // Whether or not there is tax, the total is the subtotal.
        expect($cart->getTotal())->toBe(1000);
    }
)->with([
    'neither stock nor tax' => [
        fn(FakeStockWorker $stock): BuyableInterface => new FakeBuyable(
            '1',
            1000
        ),
        false,
        false,
    ],
    'tax but no stock' => [
        fn(FakeStockWorker $stock): BuyableInterface => new FakeTaxableBuyable(
            '1',
            1000,
            new PercentageTaxRate(21)
        ),
        false,
        true,
    ],
    'stock but no tax' => [
        fn(FakeStockWorker $stock): BuyableInterface => new FakeStockedBuyable(
            '1',
            1000,
            $stock
        ),
        true,
        false,
    ],
    'both stock and tax' => [
        fn(
            FakeStockWorker $stock
        ): BuyableInterface => new FakeStockedTaxableBuyable(
            '1',
            1000,
            $stock,
            new PercentageTaxRate(21)
        ),
        true,
        true,
    ],
]);

it('sells something with no stock and no tax', function (): void {
    $buyable = new FakeBuyable('1', 1225);

    // The acceptance criterion, stated as plainly as it can be: this class
    // implements the whole contract, and neither capability is part of it.
    expect($buyable)->toBeInstanceOf(BuyableInterface::class);
    expect($buyable)->not->toBeInstanceOf(HasStockInterface::class);
    expect($buyable)->not->toBeInstanceOf(TaxableInterface::class);

    [$cart] = makeCart();
    $cart->add($buyable, 3);

    expect($cart->getSubTotal())->toBe(3675);
    expect($cart->getTax())->toBe(0);
});

it('makes each capability a kind of buyable', function (
    string $capability
): void {
    // Nothing can be taxable or counted without being for sale, so the
    // capabilities extend the contract rather than sitting beside it — which is
    // also what lets the cart narrow a buyable to one with an instanceof.
    expect(is_a($capability, BuyableInterface::class, true))->toBeTrue();
})->with([HasStockInterface::class, TaxableInterface::class]);

it('has retired the null implementations', function (string $class): void {
    // Named as strings, because ::class on a class that is gone is exactly what
    // this is checking is gone. A guard against them coming back: each existed
    // only so that a buyable without the thing could pretend to have it, and a
    // reintroduced one would quietly undo the seam these tests are here for.
    expect(class_exists($class))->toBeFalse();
})->with([
    'Tnt\Ecommerce\Stock\NullStockWorker',
    'Tnt\Ecommerce\TaxRate\NullTaxRate',
]);

/*
 * Stock.
 */

it('lets an uncounted buyable in whatever the quantity', function (): void {
    [$cart] = makeCart();
    $buyable = new FakeBuyable('1', 500);

    expect($cart->canAdd($buyable, 10_000))->toBeTrue();

    $cart->add($buyable, 10_000);

    expect($cart->getSubTotal())->toBe(5_000_000);
});

it('reports more of a counted buyable than the stock holds', function (): void {
    [$cart] = makeCart();
    $stock = new FakeStockWorker();
    $buyable = new FakeStockedBuyable('1', 500, $stock);
    $stock->increment($buyable, 2);

    expect($cart->canAdd($buyable, 3))->toBeFalse();
});

it('adds past the stock anyway when told to', function (): void {
    [$cart] = makeCart();
    $stock = new FakeStockWorker();
    $buyable = new FakeStockedBuyable('1', 500, $stock);
    $stock->increment($buyable, 2);

    // canAdd() said no, and add() does it regardless. A shop that backorders,
    // or oversells and reconciles later, is not doing anything wrong, and this
    // package is not the place that knows which shop this is. Refusing the sale
    // is one valid answer out of several, so the cart reports and the shop
    // decides.
    $cart->add($buyable, 3);

    expect($cart->items()[0]->getQuantity())->toBe(3);
    expect($cart->getSubTotal())->toBe(1500);
});

it('counts what the cart already holds towards the stock', function (): void {
    [$cart] = makeCart();
    $stock = new FakeStockWorker();
    $buyable = new FakeStockedBuyable('1', 500, $stock);
    $stock->increment($buyable, 3);

    $cart->add($buyable, 2);

    // Two more would make four, and there are three. Splitting the request up
    // does not get more out of the stock than asking for it at once would.
    expect($cart->canAdd($buyable, 2))->toBeFalse();
    expect($cart->canAdd($buyable))->toBeTrue();
});

it('stops counting a line the cart no longer holds', function (): void {
    [$cart] = makeCart();
    $stock = new FakeStockWorker();
    $buyable = new FakeStockedBuyable('1', 500, $stock);
    $stock->increment($buyable, 3);

    $cart->add($buyable, 3);
    expect($cart->canAdd($buyable))->toBeFalse();

    // The cart asks its storage what it holds rather than remembering, so
    // emptying the line frees the whole stock up again.
    $cart->remove($buyable);

    expect($cart->canAdd($buyable, 3))->toBeTrue();
});

it('treats a never-stocked buyable as unavailable', function (): void {
    [$cart] = makeCart();
    $buyable = new FakeStockedBuyable('1', 500, new FakeStockWorker());

    // A stock with no line for something does not know of it, which is not the
    // same as it being unlimited — and is exactly what NullStockWorker used to
    // get backwards for every buyable in the shop.
    expect($cart->canAdd($buyable))->toBeFalse();
});

it('answers for each counted buyable from its own stock', function (): void {
    [$cart] = makeCart();
    $stock = new FakeStockWorker();
    $plenty = new FakeStockedBuyable('1', 500, $stock);
    $scarce = new FakeStockedBuyable('2', 500, $stock);
    $stock->increment($plenty, 10);
    $stock->increment($scarce, 1);

    expect($cart->canAdd($plenty, 4))->toBeTrue();
    expect($cart->canAdd($scarce, 4))->toBeFalse();
});

/*
 * Tax.
 */

it('taxes only the lines whose buyable carries a rate', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeTaxableBuyable('1', 10_000, new PercentageTaxRate(21)));
    $cart->add(new FakeTaxableBuyable('2', 10_000, new PercentageTaxRate(6)));
    $cart->add(new FakeBuyable('3', 10_000));

    // €100 at 21% plus €100 at 6%, and nothing at all for the untaxed line.
    expect($cart->getTax())->toBe(2100 + 600);
    expect($cart->getSubTotal())->toBe(30_000);
});

it('rounds tax per line rather than on the subtotal', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeTaxableBuyable('1', 1250, new PercentageTaxRate(21)));
    $cart->add(new FakeTaxableBuyable('2', 1250, new PercentageTaxRate(21)));

    // The worked example in Tnt\Ecommerce\Money: 21% of 1250 is 262.5, which
    // rounds to 263, and two of those come to 526 — not the 525 that taxing the
    // subtotal in one go would give. A line is a figure that gets printed, so
    // the total has to be the sum of the printed figures.
    expect($cart->getSubTotal())->toBe(2500);
    expect($cart->getTax())->toBe(526);
});

it('taxes a line once, on its line total', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeTaxableBuyable('1', 1250, new PercentageTaxRate(21)), 2);

    // The same 2500 as the test above, and 525 rather than 526, because this
    // time it is one line. Quantity multiplies before the rate applies; the
    // rounding happens once, where the printed figure is.
    expect($cart->items())->toHaveCount(1);
    expect($cart->getSubTotal())->toBe(2500);
    expect($cart->getTax())->toBe(525);
});

it('reports no tax for a cart of untaxed things', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 4000), 2);
    $cart->add(new FakeBuyable('2', 1500));

    expect($cart->getTax())->toBe(0);
});

it('reports no tax for an empty cart', function (): void {
    [$cart] = makeCart();

    expect($cart->getTax())->toBe(0);
});

it('taxes a line total a coupon has not touched', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeTaxableBuyable('1', 10_000, new PercentageTaxRate(21)));
    $cart->addDiscount(FakeDiscountCode::withCoupon(new PercentageCoupon(10)));

    // €10.00 off a €100.00 cart, and the tax is still 21% of the whole line.
    // A coupon comes off the cart rather than off any line in particular, so
    // there is no line total for it to have changed and nothing that says which
    // line it would have changed. A shop quoting prices without tax in them and
    // wanting the discounted base has both figures below to work it out from.
    expect($cart->getReduction())->toBe(1000);
    expect($cart->getTax())->toBe(2100);
    expect($cart->getTotal())->toBe(9000);
});

it('keeps tax out of the total', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeTaxableBuyable('1', 10_000, new PercentageTaxRate(21)));

    // The seam reports; it does not charge. Whether a price already contains
    // its VAT decides whether this figure is part of the total or on top of it,
    // and that is not a question this package has ever recorded an answer to.
    expect($cart->getTax())->toBe(2100);
    expect($cart->getSubTotal())->toBe(10_000);
    expect($cart->getTotal())->toBe(10_000);
});
