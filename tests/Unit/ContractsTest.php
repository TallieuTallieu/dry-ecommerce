<?php

declare(strict_types=1);

/*
 * Smoke coverage for the package's public contracts.
 *
 * A cheap guard that the package's published surface stays published: an
 * interface renamed or moved without its consumers noticing shows up here
 * first. The behavioural coverage lives in CartTest and
 * FulfillmentAttributesTest.
 */

it('autoloads every public contract', function (string $contract): void {
    expect(interface_exists($contract))->toBeTrue();
})->with([
    Tnt\Ecommerce\Contracts\AttributeStorageAwareInterface::class,
    Tnt\Ecommerce\Contracts\AttributeStorageInterface::class,
    Tnt\Ecommerce\Contracts\BuyableInterface::class,
    Tnt\Ecommerce\Contracts\CartInterface::class,
    Tnt\Ecommerce\Contracts\CartItemInterface::class,
    Tnt\Ecommerce\Contracts\CartStorageInterface::class,
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
