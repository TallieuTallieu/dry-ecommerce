<?php

declare(strict_types=1);

/*
 * The payment status lifecycle, run through the real listeners.
 *
 * Until now `ecommerce_order.payment_status` was a column nothing ever wrote: a
 * paid order and an unpaid one were indistinguishable in the database, and the
 * spike that proved checkout end to end got an empty status back from an order
 * it had just "paid". The fix has three parts and each is tested here against
 * the production code — checkout() writing pending at birth, the provider's
 * listeners translating each payment event into a word on the column, and the
 * reader treating anything it cannot parse as pending.
 *
 * Like CouponRedemptionTest, the listeners under test are closures registered
 * by EcommerceServiceProvider::bootEventListeners(), so the only honest way to
 * reach them is to boot the provider and dispatch real events — that is what
 * bootEcommerce() in Pest.php does. The orders keep to memory through the
 * Cart::newOrder() seam, so all of it runs with no database.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\InMemoryOrder;
use Tests\Support\InMemoryOrderCart;
use Tests\Support\UnsavedCustomer;
use Tnt\Ecommerce\Account\GuestUserResolver;
use Tnt\Ecommerce\Cart\InMemoryCartStorage;
use Tnt\Ecommerce\Events\Order\OrderEvent;
use Tnt\Ecommerce\Events\Order\Paid;
use Tnt\Ecommerce\Events\Order\PaymentCanceled;
use Tnt\Ecommerce\Events\Order\PaymentExpired;
use Tnt\Ecommerce\Events\Order\PaymentFailed;
use Tnt\Ecommerce\Events\Order\PaymentRefunded;
use Tnt\Ecommerce\Fulfillment\InMemoryAttributeStorage;
use Tnt\Ecommerce\Payment\NullPayment;
use Tnt\Ecommerce\Payment\PaymentStatus;
use Tnt\Ecommerce\Shop\Shop;

it('gives every order a pending status from birth', function (): void {
    // makeCheckoutCart() pays through FakePayment, which records the order and
    // does nothing — standing in for an asynchronous gateway whose webhook has
    // not arrived yet. Pending is what the order holds in that window.
    bootEcommerce();

    [$cart] = makeCheckoutCart();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout(new UnsavedCustomer());

    expect($cart->placed()->payment_status)->toBe('pending');
    expect($cart->placed()->getPaymentStatus())->toBe(PaymentStatus::Pending);
});

it('writes the status each payment event reports', function (
    string $event,
    PaymentStatus $status
): void {
    $order = new InMemoryOrder();

    // The dataset hands the event class as a string, which PHPStan can
    // only read as `object` once instantiated; every class in it is an
    // OrderEvent, and the narrowing below is what says so.
    /** @var OrderEvent $payload */
    $payload = new $event($order);

    bootEcommerce()->dispatch($event, $payload);

    expect($order->payment_status)->toBe($status->value);
    expect($order->getPaymentStatus())->toBe($status);

    // Written AND saved: a status that only lived on the model instance
    // would be gone by the time a webhook's request had finished.
    expect($order->saveCount)->toBe(1);
})->with([
    'paid' => [Paid::class, PaymentStatus::Paid],
    'failed' => [PaymentFailed::class, PaymentStatus::Failed],
    'canceled' => [PaymentCanceled::class, PaymentStatus::Canceled],
    'expired' => [PaymentExpired::class, PaymentStatus::Expired],
    'refunded' => [PaymentRefunded::class, PaymentStatus::Refunded],
]);

it('ends a NullPayment checkout paid', function (): void {
    // The shipped dummy gateway, run exactly as a shop that never configured
    // ecommerce.payment runs it: checkout() writes pending, pay() dispatches
    // Paid synchronously, and the listener overwrites the status before
    // checkout() returns. This is the flow a real synchronous gateway copies.
    $dispatcher = bootEcommerce();

    $cart = new InMemoryOrderCart(
        new Shop(new InMemoryAttributeStorage()),
        new InMemoryCartStorage(),
        new NullPayment($dispatcher),
        new GuestUserResolver()
    );

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout(new UnsavedCustomer());

    $order = $cart->placed();

    expect($order->payment_status)->toBe('paid');
    expect($order->getPaymentStatus())->toBe(PaymentStatus::Paid);

    // Three saves, and the third is the one this ticket added: two from
    // checkout() as always, then the Paid listener persisting the status.
    expect($order->saveCount)->toBe(3);
});

it('reads an order from before the lifecycle as pending', function (): void {
    // A row written before anything set payment_status holds ''. It reads as
    // pending because pending is the one status that claims nothing: nothing
    // ever recorded money arriving for that order, and inventing any other
    // answer would assert a report no gateway made.
    $order = new InMemoryOrder();

    expect($order->getPaymentStatus())->toBe(PaymentStatus::Pending);
});

it('reads a word it does not know as pending', function (): void {
    // Same rule, other direction: a status written by a newer version of this
    // package, or by a gateway writing the column directly in the years the
    // package did not, is a word this reader cannot interpret — not an error.
    $order = new InMemoryOrder();
    $order->payment_status = 'authorised';

    expect($order->getPaymentStatus())->toBe(PaymentStatus::Pending);
});
