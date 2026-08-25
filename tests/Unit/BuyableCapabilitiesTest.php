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
 * Amounts are integer cents throughout, and stock quantities are whole. What the
 * tax actually comes to, under each pricing convention, is TaxTest's business;
 * the tax group here only asks which lines are taxed at all, which is the
 * capability question this file is about.
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
use Tnt\Ecommerce\Tax\PriceConvention;
use Tnt\Ecommerce\Tax\TaxPolicy;

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

        // The 21% inside €10.00, under the default inclusive convention.
        expect($cart->getTax())->toBe($taxed ? 174 : 0);

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

    // The capability question, which is all this file is about: two lines
    // carry a rate and one does not, so two are taxed and one contributes
    // nothing. What the figures come to is TaxTest's business — these are the
    // tax contained in €100 at each rate, under the default convention.
    expect($cart->getTax())->toBe(1736 + 566);
    expect($cart->getSubTotal())->toBe(30_000);
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

it('leaves a cart of untaxed things at its subtotal', function (): void {
    // A shop that sells nothing taxable is unaffected by any of this,
    // whichever convention it is configured for: there is no tax to contain
    // and none to add.
    foreach (
        [PriceConvention::Inclusive, PriceConvention::Exclusive]
        as $convention
    ) {
        [$cart] = makeCart([], null, new TaxPolicy($convention));

        $cart->add(new FakeBuyable('1', 4000), 2);

        expect($cart->getTax())->toBe(0);
        expect($cart->getTotal())->toBe(8000);
    }
});
