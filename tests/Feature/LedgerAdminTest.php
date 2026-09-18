<?php

declare(strict_types=1);

/*
 * The read-only ledger screen: bound, never registered, and unable to write.
 *
 * Runs without a booted dry admin, so it checks what the provider binds and
 * what the manager is made of — not how the admin renders it. That nothing
 * lands in admin\Router::$modules is not asserted: loading Router requires
 * the host's app/configuration/admin.inc.php.
 */

use dry\admin\Portal;
use dry\orm\action\Delete;
use dry\orm\action\DeleteAll;
use dry\orm\action\Edit;
use dry\orm\action\MultiDelete;
use Oak\Config\Repository;
use Oak\Contracts\Config\RepositoryInterface;
use Tests\Support\WebContainer;
use Tnt\Ecommerce\Admin\LedgerManager;
use Tnt\Ecommerce\Contracts\LedgerPortalInterface;
use Tnt\Ecommerce\EcommerceServiceProvider;
use Tnt\Ecommerce\Model\PaymentEntry;

it(
    'binds the ledger portal to a portal holding the ledger manager',
    function (): void {
        $app = new WebContainer();
        $app->instance(RepositoryInterface::class, new Repository([]));

        (new EcommerceServiceProvider())->register($app);

        $portal = $app->get(LedgerPortalInterface::class);

        expect($portal)->toBeInstanceOf(Portal::class);

        /** @var Portal $portal */
        expect($portal->modules)->toHaveCount(1);
        expect($portal->modules[0])->toBeInstanceOf(LedgerManager::class);
    }
);

it('offers nothing that writes', function (): void {
    $manager = new LedgerManager();

    expect($manager->actions)->not->toBeEmpty();

    foreach ($manager->actions as $action) {
        // Edit extends Create, so a plain Create would fail the first check.
        expect($action)->toBeInstanceOf(Edit::class);
        /** @var Edit $action */
        expect($action->readonly)->toBeTrue();
        expect($action)->not->toBeInstanceOf(Delete::class);
        expect($action)->not->toBeInstanceOf(MultiDelete::class);
        expect($action)->not->toBeInstanceOf(DeleteAll::class);
    }
});

it(
    'shows an amount in units and nothing for an entry without one',
    function (): void {
        $entry = new PaymentEntry();

        $entry->amount = -1225;
        expect(LedgerManager::amount($entry))->toBe('-12.25');

        $entry->amount = null;
        expect(LedgerManager::amount($entry))->toBe('');
    }
);

it('shows no order for an entry filed under none', function (): void {
    $entry = new PaymentEntry();
    $entry->order = null;

    expect(LedgerManager::order($entry))->toBe('');
});
