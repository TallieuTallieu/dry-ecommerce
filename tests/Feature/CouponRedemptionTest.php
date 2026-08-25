<?php

declare(strict_types=1);

/*
 * The coupon is redeemed when the order is paid.
 *
 * This is money logic, and until now nothing ran it. It does not live in a
 * class of its own: it is a closure registered by
 * EcommerceServiceProvider::bootEventListeners(), which means the only honest
 * way to reach it is to boot the provider and dispatch a real Paid event.
 * Copying the closure into a test would prove that the copy works.
 *
 * What it costs to do properly is two small doubles. The provider puts its
 * migrator registration behind isRunningInConsole(), and a test runner is a
 * console, so Tests\Support\WebContainer answers that the way a shop serving a
 * checkout would. The rest is a real container, a real dispatcher and the real
 * listener.
 *
 * Worth having because a coupon that silently stops being marked as used is a
 * coupon that can be spent twice, and nothing about the checkout it came
 * through would look wrong.
 */

use Oak\Contracts\Dispatcher\DispatcherInterface;
use Oak\Dispatcher\Dispatcher;
use Tests\Support\FakeCoupon;
use Tests\Support\FakeDiscountCode;
use Tests\Support\InMemoryOrder;
use Tests\Support\WebContainer;
use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Contracts\CustomerInterface;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\EcommerceServiceProvider;
use Tnt\Ecommerce\Events\Order\Paid;

/**
 * A booted package, and the dispatcher its listeners are on.
 *
 * @return DispatcherInterface
 */
function bootEcommerce(): DispatcherInterface
{
    $app = new WebContainer();

    // singleton(), so that the dispatcher the provider adds listeners to is the
    // dispatcher the event is later sent through. With set() the container
    // builds a fresh one per resolution and the listener is never reached --
    // which is a mistake a test can make, not one the framework makes:
    // Oak\Dispatcher\DispatcherServiceProvider binds this as a singleton.
    $app->singleton(DispatcherInterface::class, Dispatcher::class);

    (new EcommerceServiceProvider())->boot($app);

    /** @var DispatcherInterface $dispatcher */
    $dispatcher = $app->get(DispatcherInterface::class);

    return $dispatcher;
}

/**
 * An order carrying a discount code, as checkout would have left it.
 *
 * @param FakeCoupon|null $coupon
 * @return InMemoryOrder
 */
function paidOrderWith(?FakeCoupon $coupon): InMemoryOrder
{
    $order = new InMemoryOrder();
    $order->total = 3500;
    $order->discount =
        $coupon === null ? null : FakeDiscountCode::withCoupon($coupon);

    return $order;
}

it('redeems the coupon behind a paid order', function (): void {
    $coupon = new FakeCoupon(500);
    $order = paidOrderWith($coupon);

    bootEcommerce()->dispatch(Paid::class, new Paid($order));

    expect($coupon->redeemCount)->toBe(1);
});

it('redeems it once per payment, not once per listener', function (): void {
    $coupon = new FakeCoupon(500);
    $dispatcher = bootEcommerce();

    // A shop is free to want to hear about a payment too — mail a receipt,
    // tell a warehouse — and does so by adding its own listener next to this
    // package's. Redeeming a coupon is not something to do once per interested
    // party, so the count below has to be the count of payments and not the
    // count of listeners.
    $alsoHeard = 0;

    $dispatcher->addListener(Paid::class, function (Paid $event) use (
        &$alsoHeard
    ): void {
        $alsoHeard++;
    });

    $dispatcher->dispatch(Paid::class, new Paid(paidOrderWith($coupon)));

    // Both listeners ran, so there genuinely were two of them and the
    // assertion below is not passing for want of anything to go wrong.
    expect($alsoHeard)->toBe(1);
    expect($coupon->redeemCount)->toBe(1);
});

it('leaves a coupon that is no longer redeemable alone', function (): void {
    // The window between placing the order and paying for it is real, and a
    // coupon can run out inside it. Redeeming it anyway would spend something
    // the shop had already withdrawn.
    $coupon = new FakeCoupon(500, redeemable: false);

    bootEcommerce()->dispatch(Paid::class, new Paid(paidOrderWith($coupon)));

    expect($coupon->redeemCount)->toBe(0);
});

it('does nothing for an order that carried no discount', function (): void {
    // No discount, no coupon, nothing to redeem — and, more to the point, no
    // error on the overwhelmingly common path. Getting through the dispatch is
    // the whole assertion: reading the discount back afterwards would only
    // restate the line above it.
    bootEcommerce()->dispatch(Paid::class, new Paid(paidOrderWith(null)));
})->throwsNoExceptions();

it('takes no interest in an order it did not write', function (): void {
    // The listener narrows to this package's Order before touching a discount.
    // Anything else implementing OrderInterface has no discount column to read
    // and is passed over rather than guessed at.
    $foreign = new class implements OrderInterface {
        public function add(CartItemInterface $cartItem) {}

        public function getItems()
        {
            return [];
        }

        public function setCustomer(CustomerInterface $customer) {}

        public function getCustomer(): CustomerInterface
        {
            throw new LogicException('Not needed for this test.');
        }

        public function setFulfillment(
            FulfillmentInterface $fulfillmentMethod
        ) {}

        public function getFulfillment(): FulfillmentInterface
        {
            throw new LogicException('Not needed for this test.');
        }

        public function getSubTotal(): int
        {
            return 0;
        }

        public function getTotal(): int
        {
            return 0;
        }

        public function getReduction(): int
        {
            return 0;
        }

        public function getTax(): int
        {
            return 0;
        }
    };

    bootEcommerce()->dispatch(Paid::class, new Paid($foreign));
})->throwsNoExceptions();
