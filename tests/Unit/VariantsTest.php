<?php

declare(strict_types=1);

/*
 * Variants: a buyable sold as one of several versions, each a line of its
 * own, priced at its own price when it has one, and frozen onto the order as
 * it was sold.
 *
 * A line keeps the variant's id in a `variant` column of its own, beside the
 * options rather than in them; the order line adds the title it was sold
 * under. These tests read both back through the contracts.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeVariant;
use Tests\Support\FakeVariantBuyable;
use Tests\Support\InMemoryLineOrder;
use Tnt\Ecommerce\Cart\InMemoryCartItem;
use Tnt\Ecommerce\UnknownVariant;

function giftBasket(): FakeVariantBuyable
{
    return new FakeVariantBuyable('1', 7500, [
        new FakeVariant('40', '€ 40', 4000),
        new FakeVariant('55', '€ 55', 5500),
        new FakeVariant('75', '€ 75'),
    ]);
}

it('keeps two variants of one buyable on two lines', function (): void {
    [$cart] = makeCart();
    $basket = giftBasket();

    $cart->add($basket, 1, variant: $basket->getVariant('40'));
    $cart->add($basket, 2, variant: $basket->getVariant('55'));
    $cart->add($basket, 1, variant: $basket->getVariant('40'));

    expect($cart->items())->toHaveCount(2);
    expect($cart->items()[0]->getQuantity())->toBe(2);
    expect($cart->items()[0]->getVariant()?->getTitle())->toBe('€ 40');
    expect($cart->items()[1]->getVariant()?->getTitle())->toBe('€ 55');
});

it('prices a line at its variant, or at the buyable', function (): void {
    [$cart] = makeCart();
    $basket = giftBasket();

    $cart->add($basket, 2, variant: $basket->getVariant('40'));
    $cart->add($basket, 1, variant: $basket->getVariant('75'));

    expect($cart->items()[0]->getPrice())->toBe(8000);
    // No price of its own: the buyable's.
    expect($cart->items()[1]->getPrice())->toBe(7500);
    expect($cart->getSubTotal())->toBe(15500);
});

it('adds a line without a pick as the first variant', function (): void {
    [$cart] = makeCart();

    $cart->add(giftBasket(), 1);

    expect($cart->items()[0]->getVariantId())->toBe('40');
    expect($cart->items()[0]->getPrice())->toBe(4000);
});

it('refuses a variant the buyable does not offer', function (): void {
    [$cart] = makeCart();

    $cart->add(giftBasket(), 1, variant: new FakeVariant('99', 'Elsewhere'));
})->throws(UnknownVariant::class);

it('refuses a variant on a buyable without variants', function (): void {
    [$cart] = makeCart();

    $cart->add(
        new FakeBuyable('1', 500),
        1,
        variant: new FakeVariant('1', 'x')
    );
})->throws(UnknownVariant::class);

it('leaves a buyable without variants as it was', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 500), 2, ['size' => 'L']);

    expect($cart->items()[0]->getVariant())->toBeNull();
    expect($cart->items()[0]->getVariantId())->toBeNull();
    expect($cart->items()[0]->getPrice())->toBe(1000);
});

it('keeps the variant out of the options', function (): void {
    [$cart] = makeCart();
    $basket = giftBasket();

    // Options are the shop's alone: nothing is added to them, and nothing in
    // them — a tampered form included — picks the variant.
    $cart->add(
        $basket,
        1,
        ['variant' => '55'],
        variant: $basket->getVariant('40')
    );

    expect($cart->items()[0]->getOptions())->toBe(['variant' => '55']);
    expect($cart->items()[0]->getVariantId())->toBe('40');
});

it('prices a withdrawn variant at the buyable', function (): void {
    $basket = giftBasket();
    $line = new InMemoryCartItem('a', $basket, 1, [], '40');

    $basket->withdraw('40');

    expect($line->getVariant())->toBeNull();
    expect($line->getVariantId())->toBe('40');
    expect($line->getPrice())->toBe(7500);
});

it('freezes the variant onto the order line as it was sold', function (): void {
    $order = new InMemoryLineOrder();

    $order->add(
        new InMemoryCartItem('1', giftBasket(), 3, ['gift' => true], '55')
    );

    $line = $order->writtenLines[0];

    expect($line->price)->toBe(16500);
    expect($line->variant)->toBe('55');
    expect($line->variant_title)->toBe('€ 55');
    expect($line->getVariant()?->getId())->toBe('55');
    expect($line->getVariant()?->getTitle())->toBe('€ 55');
    // The unit price, read back off the frozen line total.
    expect($line->getVariant()?->getPrice())->toBe(5500);
    // The shop's own options ride along untouched, and alone.
    expect($line->getOptions())->toBe(['gift' => true]);
    expect($line->saveCount)->toBe(1);
});

it(
    'keeps the frozen variant when the shop withdraws it later',
    function (): void {
        $basket = giftBasket();
        $order = new InMemoryLineOrder();

        $order->add(new InMemoryCartItem('1', $basket, 1, [], '40'));
        $basket->withdraw('40');

        expect($order->writtenLines[0]->getVariant()?->getTitle())->toBe(
            '€ 40'
        );
    }
);

it('freezes a variant withdrawn before checkout by its id', function (): void {
    $basket = giftBasket();
    $order = new InMemoryLineOrder();
    $cartLine = new InMemoryCartItem('1', $basket, 2, [], '40');

    $basket->withdraw('40');
    $order->add($cartLine);

    $line = $order->writtenLines[0];

    // Charged the buyable's price, and still saying which one was picked.
    expect($line->price)->toBe(15000);
    expect($line->getVariant()?->getId())->toBe('40');
    expect($line->getVariant()?->getTitle())->toBe('');
    expect($line->getVariant()?->getPrice())->toBe(7500);
});

it('writes no variant for a buyable without them', function (): void {
    $order = new InMemoryLineOrder();

    $order->add(new InMemoryCartItem('1', new FakeBuyable('1', 500), 1));

    expect($order->writtenLines[0]->variant)->toBeNull();
    expect($order->writtenLines[0]->variant_title)->toBeNull();
    expect($order->writtenLines[0]->getVariant())->toBeNull();
});
