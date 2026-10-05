<?php

declare(strict_types=1);

/*
 * Variants: a buyable sold as one of several versions, each a line of its
 * own, priced at its own price when it has one, and frozen onto the order as
 * it was sold.
 *
 * Every line here goes through the configured storage — the options one by
 * default — so these are tests of what a shop sees through the contracts, not
 * of where the variant happens to be written.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeTableVariantStorage;
use Tests\Support\FakeVariant;
use Tests\Support\FakeVariantBuyable;
use Tests\Support\InMemoryLineOrder;
use Tnt\Ecommerce\Cart\InMemoryCartItem;
use Tnt\Ecommerce\Cart\OptionsVariantStorage;
use Tnt\Ecommerce\Cart\Variants;
use Tnt\Ecommerce\UnknownVariant;

// The storage is static, set once per process by the service provider; a test
// that swaps it must not leak the swap into the next one.
beforeEach(fn() => Variants::useStorage(new OptionsVariantStorage()));
afterEach(fn() => Variants::useStorage(new OptionsVariantStorage()));

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

    expect($cart->items()[0]->getVariant()?->getId())->toBe('40');
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
    expect($cart->items()[0]->getOptions())->toBe(['size' => 'L']);
    expect($cart->items()[0]->getPrice())->toBe(1000);
});

it('does not let posted options pick a variant', function (): void {
    [$cart] = makeCart();

    // A tampered form naming the reserved key gets the first variant, not the
    // one it wrote in.
    $cart->add(giftBasket(), 1, [Variants::KEY => ['id' => '55']]);

    expect($cart->items()[0]->getVariant()?->getId())->toBe('40');
});

it('prices a withdrawn variant at the buyable', function (): void {
    $basket = giftBasket();
    $options = Variants::forCart([], $basket->getVariant('40'));
    $line = new InMemoryCartItem('a', $basket, 1, $options);

    $basket->withdraw('40');

    expect($line->getVariant())->toBeNull();
    expect($line->getPrice())->toBe(7500);
});

it('freezes the variant onto the order line as it was sold', function (): void {
    $basket = giftBasket();
    $order = new InMemoryLineOrder();

    $order->add(
        new InMemoryCartItem(
            '1',
            $basket,
            3,
            Variants::forCart(['gift' => true], $basket->getVariant('55'))
        )
    );

    $line = $order->writtenLines[0];

    expect($line->price)->toBe(16500);
    expect($line->getVariant()?->getId())->toBe('55');
    expect($line->getVariant()?->getTitle())->toBe('€ 55');
    expect($line->getVariant()?->getPrice())->toBe(5500);
    // The shop's own options ride along untouched.
    expect($line->getOptions()['gift'])->toBeTrue();
});

it(
    'keeps the frozen variant when the shop renames it later',
    function (): void {
        $basket = giftBasket();
        $order = new InMemoryLineOrder();

        $order->add(
            new InMemoryCartItem(
                '1',
                $basket,
                1,
                Variants::forCart([], $basket->getVariant('40'))
            )
        );
        $basket->withdraw('40');

        expect($order->writtenLines[0]->getVariant()?->getTitle())->toBe(
            '€ 40'
        );
    }
);

it('freezes a variant withdrawn before checkout by its id', function (): void {
    $basket = giftBasket();
    $order = new InMemoryLineOrder();
    $cartLine = new InMemoryCartItem(
        '1',
        $basket,
        2,
        Variants::forCart([], $basket->getVariant('40'))
    );

    $basket->withdraw('40');
    $order->add($cartLine);

    $line = $order->writtenLines[0];

    // Charged the buyable's price, and still saying which one was picked.
    expect($line->price)->toBe(15000);
    expect($line->getVariant()?->getId())->toBe('40');
    expect($line->getVariant()?->getTitle())->toBe('');
    expect($line->getVariant()?->getPrice())->toBe(7500);
});

it('freezes through whichever storage is configured', function (): void {
    $storage = new FakeTableVariantStorage();
    Variants::useStorage($storage);

    $basket = giftBasket();
    $order = new InMemoryLineOrder();

    $order->add(
        new InMemoryCartItem(
            '1',
            $basket,
            2,
            Variants::forCart([], $basket->getVariant('40'))
        )
    );

    $line = $order->writtenLines[0];

    // Kept beside the line, not in it: the options still hold only the
    // reference the cart merged on.
    expect($storage->rows)->toHaveCount(1);
    expect($line->getVariant()?->getTitle())->toBe('€ 40');
    expect($line->getVariant()?->getPrice())->toBe(4000);
    expect($line->getOptions())->toBe([Variants::KEY => ['id' => '40']]);
});

it('saves an order line without a variant once', function (): void {
    $order = new InMemoryLineOrder();

    $order->add(new InMemoryCartItem('1', new FakeBuyable('1', 500), 1));

    expect($order->writtenLines[0]->saveCount)->toBe(1);
    expect($order->writtenLines[0]->getVariant())->toBeNull();
});
