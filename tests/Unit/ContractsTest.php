<?php

declare(strict_types=1);

/*
 * Smoke coverage for the package's public contracts.
 *
 * The interfaces under Tnt\Ecommerce\Contracts are the only part of the package
 * that can be autoloaded without a booted Dry application, so they are what a
 * near-empty suite can assert today. The redesign tickets replace this file
 * with real behavioural coverage.
 */

it('autoloads every public contract', function (string $contract): void {
    expect(interface_exists($contract))->toBeTrue();
})->with([
    Tnt\Ecommerce\Contracts\BuyableInterface::class,
    Tnt\Ecommerce\Contracts\CartInterface::class,
    Tnt\Ecommerce\Contracts\CartItemInterface::class,
    Tnt\Ecommerce\Contracts\CouponInterface::class,
    Tnt\Ecommerce\Contracts\CustomerInterface::class,
    Tnt\Ecommerce\Contracts\FulfillmentInterface::class,
    Tnt\Ecommerce\Contracts\OrderInterface::class,
    Tnt\Ecommerce\Contracts\OrderItemInterface::class,
    Tnt\Ecommerce\Contracts\PaymentInterface::class,
    Tnt\Ecommerce\Contracts\ShopInterface::class,
    Tnt\Ecommerce\Contracts\StockWorkerInterface::class,
    Tnt\Ecommerce\Contracts\TaxRateInterface::class,
    Tnt\Ecommerce\Contracts\TotalingInterface::class,
]);
