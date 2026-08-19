<?php

declare(strict_types=1);

/*
 * Guards the one thing the test runner itself depends on: that the package's
 * classes can be autoloaded outside a booted Dry application.
 *
 * dry\db\Connection has file-level side effects and fatals on autoload when
 * dry\Dry::$root is uninitialised, so any test that reaches the database layer
 * needs a seam first. Tracking that seam is the CartStorageInterface work later
 * in this epic; until then this test pins down what is safe to touch.
 */

it('autoloads the service provider without booting Dry', function (): void {
    expect(
        class_exists(Tnt\Ecommerce\EcommerceServiceProvider::class)
    )->toBeTrue();
});

it('autoloads the null implementations without booting Dry', function (
    string $class
): void {
    expect(class_exists($class))->toBeTrue();
})->with([
    Tnt\Ecommerce\Payment\NullPayment::class,
    Tnt\Ecommerce\Stock\NullStockWorker::class,
    Tnt\Ecommerce\TaxRate\NullTaxRate::class,
]);
