<?php

declare(strict_types=1);

/*
 * A buyable whose options set its price.
 *
 * Options carry what was chosen, and for most buyables that is all they do: a
 * line costs quantity × getPrice(). PricedByOptionsInterface is the opt-in
 * for the buyable whose choice is the price — a gift basket of € 40 or € 75 —
 * and both line implementations, the row and the in-memory one, have to ask
 * it, or a cart totals one way in a test and another in a shop.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeSizedBuyable;
use Tests\Support\InMemoryLineOrder;
use Tests\Support\PercentageTaxRate;
use Tnt\Ecommerce\Cart\InMemoryCartItem;
use Tnt\Ecommerce\Cart\LineOptions;
use Tnt\Ecommerce\Contracts\TaxableInterface;
use Tnt\Ecommerce\Contracts\TaxRateInterface;
use Tnt\Ecommerce\Model\CartItem;

function sizedBasket(): FakeSizedBuyable
{
    return new FakeSizedBuyable('1', 7500, ['S' => 4000, 'M' => 5500]);
}

it('prices an in-memory line by its options', function (): void {
    $line = new InMemoryCartItem('a', sizedBasket(), 2, ['size' => 'S']);

    expect($line->getPrice())->toBe(8000);
});

it('prices a row-backed line by its options', function (): void {
    $line = new CartItem();
    $line->setBuyable(sizedBasket());
    $line->quantity = 3;
    $line->options = LineOptions::canonical(['size' => 'M']);

    expect($line->getPrice())->toBe(16500);
});

it(
    'leaves the price to the buyable for options it does not price',
    function (): void {
        expect(
            (new InMemoryCartItem('a', sizedBasket(), 1, [
                'size' => 'XL',
            ]))->getPrice()
        )->toBe(7500);
        expect((new InMemoryCartItem('b', sizedBasket(), 1))->getPrice())->toBe(
            7500
        );
    }
);

it('never asks a plain buyable about its options', function (): void {
    $line = new InMemoryCartItem('a', new FakeBuyable('1', 500), 2, [
        'size' => 'S',
    ]);

    expect($line->getPrice())->toBe(1000);
});

it(
    'totals differently-priced variants of one buyable apart',
    function (): void {
        [$cart] = makeCart();
        $basket = sizedBasket();

        $cart->add($basket, 1, ['size' => 'S']);
        $cart->add($basket, 2, ['size' => 'M']);

        expect($cart->items())->toHaveCount(2);
        expect($cart->getSubTotal())->toBe(4000 + 2 * 5500);
    }
);

it('taxes a line on its option price', function (): void {
    [$cart] = makeCart();

    $basket = new class ('1', 7500, ['S' => 4000])
        extends FakeSizedBuyable
        implements TaxableInterface
    {
        public function getTaxRate(): TaxRateInterface
        {
            return new PercentageTaxRate(21);
        }
    };

    $cart->add($basket, 2, ['size' => 'S']);

    // The tax contained in €80 at 21% under the default convention — on the
    // option price, not the €150 the base price would have made it.
    expect($cart->getTax())->toBe(1388);
});

it('freezes the option price onto the order line', function (): void {
    $order = new InMemoryLineOrder();

    $order->add(new InMemoryCartItem('1', sizedBasket(), 2, ['size' => 'M']));

    expect($order->writtenLines[0]->price)->toBe(11000);
});
