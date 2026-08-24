<?php

declare(strict_types=1);

/*
 * Guards the one thing the test runner itself depends on: that the package's
 * classes can be autoloaded outside a booted Dry application.
 *
 * dry\db\Connection has file-level side effects and fatals on autoload when
 * dry\Dry::$root is uninitialised, so any class that resolves it at load time
 * is off limits to the unit suite. The list below is therefore not decoration:
 * it records what tests/Unit is allowed to touch.
 *
 * The seam this epic asked for is in place, so the domain classes are now on
 * that list — Cart, both cart storages, the fulfillment attribute storages and
 * the repositories all load without a connection. What still needs one is
 * *running* a query, which is why the repository tests only build repositories
 * and never execute them.
 */

it('autoloads the service provider without booting Dry', function (): void {
    expect(
        class_exists(Tnt\Ecommerce\EcommerceServiceProvider::class)
    )->toBeTrue();
});

it('autoloads the null payment without booting Dry', function (): void {
    // The last of the null implementations, and the only one that was ever a
    // real default: a shop with no payment package installed has to be able to
    // resolve *something* for PaymentInterface. NullStockWorker and NullTaxRate
    // were not defaults in that sense — they stood in for answers a buyable
    // should never have been asked for, and are gone.
    expect(class_exists(Tnt\Ecommerce\Payment\NullPayment::class))->toBeTrue();
});

it('autoloads the cart seam without booting Dry', function (
    string $class
): void {
    expect(class_exists($class))->toBeTrue();
})->with([
    Tnt\Ecommerce\Account\GuestUserResolver::class,
    Tnt\Ecommerce\Cart\Cart::class,
    Tnt\Ecommerce\Cart\InMemoryCartItem::class,
    Tnt\Ecommerce\Cart\InMemoryCartStorage::class,
    Tnt\Ecommerce\Cart\SessionCartStorage::class,
    Tnt\Ecommerce\Fulfillment\InMemoryAttributeStorage::class,
    Tnt\Ecommerce\Model\CartItem::class,
    Tnt\Ecommerce\Model\OrderItem::class,
    Tnt\Ecommerce\Fulfillment\SessionAttributeStorage::class,
    Tnt\Ecommerce\Shop\Shop::class,
    Tnt\Ecommerce\Stock\StockWorker::class,
    Tnt\Ecommerce\Stock\StockWouldGoNegative::class,
]);

it('autoloads every repository without booting Dry', function (
    string $class
): void {
    expect(class_exists($class))->toBeTrue();
})->with([
    Tnt\Ecommerce\Repository\CartItemRepository::class,
    Tnt\Ecommerce\Repository\CartRepository::class,
    Tnt\Ecommerce\Repository\CustomerRepository::class,
    Tnt\Ecommerce\Repository\DiscountCodeRepository::class,
    Tnt\Ecommerce\Repository\OrderItemRepository::class,
    Tnt\Ecommerce\Repository\OrderRepository::class,
    Tnt\Ecommerce\Repository\StockItemRepository::class,
    Tnt\Ecommerce\Repository\StockRepository::class,
]);

it('leaves no static Session call in the domain layer', function (): void {
    $offenders = [];
    $root = dirname(__DIR__, 2) . '/src';

    $files = new RegexIterator(
        new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)),
        '/\.php$/'
    );

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        $path = $file->getPathname();
        $contents = file_get_contents($path);

        if ($contents !== false && str_contains($contents, 'Session::')) {
            $offenders[] = substr($path, strlen($root) + 1);
        }
    }

    expect($offenders)->toBe([]);
});
