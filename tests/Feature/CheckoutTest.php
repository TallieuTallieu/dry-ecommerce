<?php

declare(strict_types=1);

/*
 * Checkout, run end to end.
 *
 * Cart::checkout() is where every other decision in this package lands. The
 * money is worked out elsewhere and frozen onto a row here; the stock and tax
 * capabilities report elsewhere and are recorded here; the account link
 * resolved by sc-11171 is attached here. Until now none of it ran under test,
 * because the method builds an Order and saves it, and the suite has no
 * database — by design, and worth keeping.
 *
 * What made it testable is one protected method, Cart::newOrder(), overridden
 * by Tests\Support\InMemoryOrderCart to hand back an order that keeps to
 * memory. Everything after that line is the production code: the same nine
 * assignments onto the row, the same order_id, the same loop over the lines,
 * the same event and the same payment call.
 *
 * The first test is the one this was all for. checkout() copies four money
 * values onto the row by hand, and before this there was nothing anywhere in
 * the package that would notice if two of them were swapped. The amounts below
 * are four different numbers on purpose: any transposition fails.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeCoupon;
use Tests\Support\FakeDiscountCode;
use Tests\Support\FakeFulfillment;
use Tests\Support\FakeUserResolver;
use Tests\Support\InMemoryOrder;
use Tests\Support\UnsavedCustomer;
use Tests\Support\FakeTaxableBuyable;
use Tests\Support\PercentageTaxRate;
use Tnt\Ecommerce\Events\Order\Created;
use Tnt\Ecommerce\Tax\PriceConvention;
use Tnt\Ecommerce\Tax\TaxPolicy;

beforeEach(function (): void {
    // The only thing checkout() needs that is not a database: the Dispatcher
    // facade wants a container. A real container with a real dispatcher costs
    // three lines and no connection, which is why the event stayed in
    // checkout() rather than being pushed behind a seam of its own.
    $app = new Oak\Container\Container();

    Oak\Facade::setContainer($app);

    // singleton(), not set(): set() builds a fresh dispatcher on every
    // resolution, so a listener registered through the facade would be
    // registered on one instance and the dispatch would go to another.
    $app->singleton(
        Oak\Contracts\Dispatcher\DispatcherInterface::class,
        Oak\Dispatcher\Dispatcher::class
    );
});

it('freezes each cart amount onto its own column', function (): void {
    $fulfillment = new FakeFulfillment('post', 475);
    [$cart] = makeCheckoutCart([$fulfillment]);

    $cart->add(new FakeBuyable('1', 2000), 2);
    $cart->setFulfillment($fulfillment);
    $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(500)));

    // Four distinct amounts: 4000 subtotal, 475 delivery, 500 off, 3975 total.
    // No two are equal, so a swapped pair cannot pass unnoticed.
    $cart->checkout(new UnsavedCustomer());

    $order = $cart->placed();

    expect($order->subtotal)->toBe(4000);
    expect($order->fulfillment_cost)->toBe(475);
    expect($order->reduction)->toBe(500);
    expect($order->total)->toBe(3975);

    // And the row agrees with the cart it came from, rather than merely being
    // self-consistent.
    expect($order->total)->toBe($cart->getTotal());
    expect($order->subtotal)->toBe($cart->getSubTotal());
    expect($order->reduction)->toBe($cart->getReduction());
    expect($order->fulfillment_cost)->toBe($cart->getFulfillmentCost());
});

it(
    'freezes the tax and the convention it was worked out under',
    function (): void {
        [$cart] = makeCheckoutCart(
            [],
            null,
            new TaxPolicy(PriceConvention::Exclusive)
        );

        $cart->add(
            new FakeTaxableBuyable('1', 1250, new PercentageTaxRate(21))
        );
        $cart->checkout(new UnsavedCustomer());

        $order = $cart->placed();

        expect($order->tax)->toBe(263);
        expect($order->total)->toBe(1513);

        // The convention travels with the order, not only the figures. Without it
        // a shop that switches to inclusive prices next year would reprint this
        // invoice as 1250 with 217 of VAT inside -- a different total from the one
        // the customer was charged.
        expect($order->getPriceConvention())->toBe(PriceConvention::Exclusive);
        expect($order->getTax())->toBe(263);
    }
);

it('freezes an inclusive order without moving its total', function (): void {
    [$cart] = makeCheckoutCart(
        [],
        null,
        new TaxPolicy(PriceConvention::Inclusive)
    );

    $cart->add(new FakeTaxableBuyable('1', 1250, new PercentageTaxRate(21)));
    $cart->checkout(new UnsavedCustomer());

    expect($cart->placed()->tax)->toBe(217);
    expect($cart->placed()->total)->toBe(1250);
    expect($cart->placed()->getPriceConvention())->toBe(
        PriceConvention::Inclusive
    );
});

it('reads an order written before the column existed', function (): void {
    // A row from before sc-11195 has no convention recorded. It reports
    // inclusive, which is the reading whose totals match what those rows
    // already hold -- their tax was never added to anything.
    $order = new InMemoryOrder();

    expect($order->getPriceConvention())->toBe(PriceConvention::Inclusive);
});

it('records what the order was placed against', function (): void {
    $fulfillment = new FakeFulfillment('post', 475);
    [$cart] = makeCheckoutCart([$fulfillment]);
    $customer = new UnsavedCustomer();
    $discount = FakeDiscountCode::withCoupon(new FakeCoupon(500));

    $cart->add(new FakeBuyable('1', 2000));
    $cart->setFulfillment($fulfillment);
    $cart->addDiscount($discount);

    $cart->checkout($customer);

    expect($cart->placed()->customer)->toBe($customer);
    expect($cart->placed()->fulfillment_method)->toBe('post');
    expect($cart->placed()->discount)->toBe($discount);
});

it(
    'leaves the fulfillment method null when none was chosen',
    function (): void {
        [$cart] = makeCheckoutCart();

        $cart->add(new FakeBuyable('1', 2000));
        $cart->checkout(new UnsavedCustomer());

        expect($cart->placed()->fulfillment_method)->toBeNull();
        expect($cart->placed()->fulfillment_cost)->toBe(0);
        expect($cart->placed()->total)->toBe(2000);
    }
);

it('hands every line to the order exactly once', function (): void {
    [$cart] = makeCheckoutCart();

    $cart->add(new FakeBuyable('1', 2000), 2);
    $cart->add(new FakeBuyable('2', 350), 3);

    $cart->checkout(new UnsavedCustomer());

    $lines = $cart->placed()->lines;

    expect($lines)->toHaveCount(2);
    expect($lines[0]->getPrice())->toBe(4000);
    expect($lines[0]->getQuantity())->toBe(2);
    expect($lines[1]->getPrice())->toBe(1050);
    expect($lines[1]->getQuantity())->toBe(3);

    // The lines add up to the subtotal that was frozen. Copying them and
    // totalling them are separate pieces of code, and this is the one place
    // they are checked against each other.
    expect($lines[0]->getPrice() + $lines[1]->getPrice())->toBe(
        $cart->placed()->subtotal
    );
});

it('hands each line over with the options it was added with', function (): void {
    [$cart] = makeCheckoutCart();

    $cart->add(new FakeBuyable('1', 2000), 1, ['cheese' => 'no goat']);
    $cart->add(new FakeBuyable('1', 2000), 1, ['cheese' => 'no blue']);

    $cart->checkout(new UnsavedCustomer());

    $lines = $cart->placed()->lines;

    // Two variants of one buyable are two order lines, each carrying its own
    // selection into Order::add() — the freezing itself is OrderLineTest's
    // subject; what checkout owes is that nothing merges or drops on the way.
    expect($lines)->toHaveCount(2);
    expect($lines[0]->getOptions())->toBe(['cheese' => 'no goat']);
    expect($lines[1]->getOptions())->toBe(['cheese' => 'no blue']);
});

it('builds an order id on the id the first save gave it', function (): void {
    [$cart] = makeCheckoutCart();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout(new UnsavedCustomer());

    $order = $cart->placed();

    // Saved twice, and the second is not ceremony: order_id needs the id, and
    // the id only exists once the row has been written.
    expect($order->saveCount)->toBe(2);
    expect($order->order_id)->toStartWith($order->id . '-');

    // One fixed shape, every time. The `+` and the exact count are the point:
    // the generator this replaced varied the split and left the tail empty
    // roughly one order in four, so a quarter of references ended on a bare
    // underscore and read as truncated.
    expect($order->order_id)->toMatch('/^\d+-[0-9A-HJKMNP-TV-Z]{10}$/');
});

it(
    'never puts a letter that reads as a digit in a reference',
    function (): void {
        // A reference is read down a telephone and copied off a printed invoice,
        // so the pairs that get confused there are left out of the alphabet
        // entirely: I and 1, O and 0, L and 1. U goes too, which is what stops a
        // random string spelling something a customer would rather not read out.
        $references = [];

        for ($i = 0; $i < 200; $i++) {
            [$cart] = makeCheckoutCart();
            $cart->add(new FakeBuyable('1', 2000));
            $cart->checkout(new UnsavedCustomer());

            $references[] = $cart->placed()->order_id;
        }

        foreach ($references as $reference) {
            expect($reference)->not->toMatch('/[ILOU]/');
            expect($reference)->toMatch('/^\d+-[0-9A-HJKMNP-TV-Z]{10}$/');
        }
    }
);

it('does not hand two orders the same reference', function (): void {
    // Every order here is row 1, so the id prefix is identical and only the
    // random part can tell them apart — which is the part that has to be
    // doing the work. Duplicates at this sample size would mean the source is
    // not varying, the failure the old rand() would eventually have produced.
    $references = [];

    for ($i = 0; $i < 200; $i++) {
        [$cart] = makeCheckoutCart();
        $cart->add(new FakeBuyable('1', 2000));
        $cart->checkout(new UnsavedCustomer());

        $references[] = $cart->placed()->order_id;
    }

    expect(array_unique($references))->toHaveCount(200);
});

it('draws a reference from a source worth trusting', function (): void {
    // The one thing about this fix that no behavioural test can see. A
    // reference built from rand() looks perfectly random from out here and
    // passes every assertion above; what is wrong with it is that Mersenne
    // Twister's next output can be worked out from its previous ones, so
    // somebody who has placed a few orders can predict other people's
    // references without guessing. The only way to hold that is to say which
    // functions the cart is not allowed to reach for.
    $source = file_get_contents(dirname(__DIR__, 2) . '/src/Cart/Cart.php');
    expect($source)->not->toBeFalse();

    // Comments stripped first. The docblock on newOrderReference() explains at
    // length why rand() is the wrong source, and a check that read the prose
    // would trip over the explanation for the rule it is enforcing.
    $code = '';

    foreach (token_get_all((string) $source) as $token) {
        if (
            is_array($token) &&
            in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
        ) {
            continue;
        }

        $code .= is_array($token) ? $token[1] : $token;
    }

    expect($code)->toContain('random_int(');
    expect($code)->not->toMatch('/\brand\(/');
    expect($code)->not->toMatch('/\bmt_rand\(/');
    expect($code)->not->toContain('Str::random(');
});

it('announces the order once it is whole', function (): void {
    [$cart] = makeCheckoutCart();
    $announced = [];

    Oak\Dispatcher\Facade\Dispatcher::addListener(Created::class, function (
        Created $event
    ) use (&$announced): void {
        // Read the order as the listener sees it, not afterwards. A listener
        // that ran too early would see an order that is not yet the one that
        // was placed, and reading it here is the only way to tell.
        $order = $event->getOrder();

        $announced[] = [
            'total' => $order->getTotal(),
            'lines' => count(
                $order instanceof InMemoryOrder ? $order->lines : []
            ),
        ];
    });

    $cart->add(new FakeBuyable('1', 2000), 2);
    $cart->checkout(new UnsavedCustomer());

    // Announced once, and announced whole: the totals are frozen and every
    // line is on it by the time anything downstream hears about the order.
    expect($announced)->toHaveCount(1);
    expect($announced[0])->toBe(['total' => 4000, 'lines' => 1]);
});

it('runs the callback and then takes payment', function (): void {
    [$cart, , $payment] = makeCheckoutCart();
    $seen = [];

    $cart->add(new FakeBuyable('1', 2000));

    $order = $cart->checkout(new UnsavedCustomer(), function ($given) use (
        &$seen,
        $payment
    ): void {
        // Asserted here rather than afterwards, because afterwards cannot tell
        // the two orders apart: both calls have happened by then whichever way
        // round they went. The callback is where a shop stamps something onto
        // the order it is about to be charged for, so running after the payment
        // would be too late for the thing it is there to do.
        expect($payment->paid)->toBe([]);

        $seen[] = $given;
    });

    expect($seen)->toBe([$order]);
    expect($payment->paid)->toBe([$order]);
    expect($order)->toBe($cart->placed());
});

it('checks out a guest with nobody signed in', function (): void {
    [$cart] = makeCheckoutCart();
    $customer = new UnsavedCustomer();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout($customer);

    // The path sc-11171 could not reach: no account, so no link, and the order
    // still carries the customer.
    expect($customer->getUserId())->toBeNull();
    expect($cart->placed()->customer)->toBe($customer);
    expect($cart->placed()->total)->toBe(2000);
});

it('checks out an account and links the customer to it', function (): void {
    [$cart] = makeCheckoutCart([], new FakeUserResolver(42));
    $customer = new UnsavedCustomer();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout($customer);

    // The other path, through the same call. Nothing about the order differs
    // except that the customer row now knows which account placed it.
    expect($customer->getUserId())->toBe(42);
    expect($cart->placed()->customer)->toBe($customer);
    expect($cart->placed()->total)->toBe(2000);
});
