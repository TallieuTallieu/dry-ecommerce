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
    Tnt\Ecommerce\Contracts\AddressInterface::class,
    Tnt\Ecommerce\Contracts\AttributeStorageAwareInterface::class,
    Tnt\Ecommerce\Contracts\AttributeStorageInterface::class,
    Tnt\Ecommerce\Contracts\BuyableInterface::class,
    Tnt\Ecommerce\Contracts\CartInterface::class,
    Tnt\Ecommerce\Contracts\CartItemInterface::class,
    Tnt\Ecommerce\Contracts\CartStorageInterface::class,
    Tnt\Ecommerce\Contracts\CouponInterface::class,
    Tnt\Ecommerce\Contracts\CustomerInterface::class,
    Tnt\Ecommerce\Contracts\FulfillmentInterface::class,
    Tnt\Ecommerce\Contracts\HasAddressesInterface::class,
    Tnt\Ecommerce\Contracts\HasStockInterface::class,
    Tnt\Ecommerce\Contracts\OrderInterface::class,
    Tnt\Ecommerce\Contracts\OrderItemInterface::class,
    Tnt\Ecommerce\Contracts\PaymentInterface::class,
    Tnt\Ecommerce\Contracts\ShopInterface::class,
    Tnt\Ecommerce\Contracts\StockWorkerInterface::class,
    Tnt\Ecommerce\Contracts\TaxableInterface::class,
    Tnt\Ecommerce\Contracts\TaxRateInterface::class,
    Tnt\Ecommerce\Contracts\TotalingInterface::class,
    Tnt\Ecommerce\Contracts\UserResolverInterface::class,
]);

it('autoloads the money helper', function (): void {
    expect(class_exists(Tnt\Ecommerce\Money::class))->toBeTrue();
});

it('autoloads every published exception', function (string $class): void {
    // Published surface as much as the contracts are: a caller catches these
    // by name, so renaming one silently is the same kind of break.
    expect(class_exists($class))->toBeTrue();
    expect(is_a($class, InvalidArgumentException::class, true))->toBeTrue();
})->with([
    Tnt\Ecommerce\AmountTooLarge::class,
    Tnt\Ecommerce\NotAnAddressType::class,
    Tnt\Ecommerce\NotAnAmount::class,
    Tnt\Ecommerce\UnsupportedRate::class,
]);

it('autoloads the two kinds of address', function (string $class): void {
    // Both implement AddressInterface and the difference between them is the
    // subject of sc-11172: Address is a row in an editable book, FrozenAddress
    // is the copy an order took and is the only one safe on an invoice.
    expect(class_exists($class))->toBeTrue();
    expect(
        is_a($class, Tnt\Ecommerce\Contracts\AddressInterface::class, true)
    )->toBeTrue();
})->with([
    Tnt\Ecommerce\Address\FrozenAddress::class,
    Tnt\Ecommerce\Model\Address::class,
]);

it('publishes the address type as an enum', function (): void {
    expect(enum_exists(Tnt\Ecommerce\Address\AddressType::class))->toBeTrue();
});
