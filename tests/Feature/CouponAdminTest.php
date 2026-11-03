<?php

declare(strict_types=1);

/*
 * The coupon screens, as far as they can be checked without a booted dry
 * admin: what the managers are made of and what their columns show — as
 * LedgerAdminTest does for the ledger.
 */

use dry\orm\action\Create;
use dry\orm\action\Delete;
use dry\orm\action\DeleteAll;
use dry\orm\action\Edit;
use dry\orm\action\MultiDelete;
use dry\orm\Manager;
use Tnt\Ecommerce\Admin\CouponManager;
use Tnt\Ecommerce\Admin\CouponRedemptionManager;
use Tnt\Ecommerce\Admin\CouponSearcher;
use Tnt\Ecommerce\Model\Coupon;
use Tnt\Ecommerce\Model\CouponRedemption;
use Tnt\Ecommerce\Model\Order;

it('creates and edits coupons, and deletes none', function (): void {
    $manager = new CouponManager();

    $kinds = array_map(
        static fn(mixed $action): string => is_object($action)
            ? $action::class
            : '',
        $manager->actions
    );

    expect($kinds)->toBe([Create::class, Edit::class]);

    foreach ($manager->actions as $action) {
        expect($action)->not->toBeInstanceOf(Delete::class);
        expect($action)->not->toBeInstanceOf(MultiDelete::class);
        expect($action)->not->toBeInstanceOf(DeleteAll::class);
    }
});

it('lists a coupon\'s redemptions on a page of their own', function (): void {
    $manager = new CouponManager();

    expect($manager->subpages)->toHaveCount(1);
    expect($manager->subpages[0]->id)->toBe('redemptions');
    expect($manager->subpages[0]->offers_to_save())->toBeFalse();
});

it('writes nothing on the redemptions list', function (): void {
    expect((new CouponRedemptionManager())->actions)->toBe([]);
});

it('shows a coupon\'s value, balance, uses and validity', function (): void {
    $percentage = Coupon::percentage('7,5');
    $fixed = Coupon::fixed(2500);
    $fixed->balance = 1250;

    expect(CouponManager::value($percentage))->toBe('7.5%');
    expect(CouponManager::value($fixed))->toBe('25.00');
    expect(CouponManager::balance($percentage))->toBe('');
    expect(CouponManager::balance($fixed))->toBe('12.50');
    expect(CouponManager::uses($percentage))->toBe('0 / 1');
    expect(CouponManager::uses($fixed))->toBe('0');
    expect(CouponManager::validity($fixed))->toBe('always');

    $fixed->starts = (int) strtotime('2026-06-01 00:00:00');
    expect(CouponManager::validity($fixed))->toBe('from 01/06/2026');

    $fixed->ends = (int) strtotime('2026-06-30 23:59:59');
    expect(CouponManager::validity($fixed))->toBe('01/06/2026 – 30/06/2026');

    $fixed->starts = null;
    expect(CouponManager::validity($fixed))->toBe('until 30/06/2026');
});

it('shows a redemption\'s order, amount and shortfall', function (): void {
    $order = new Order();
    $order->id = 12;
    $order->order_id = '2600012';

    $redemption = new CouponRedemption();
    $redemption->order = $order;
    $redemption->amount = 3000;
    $redemption->shortfall = null;

    expect(CouponRedemptionManager::order($redemption))->toBe('12 · 2600012');
    expect(CouponRedemptionManager::amount($redemption))->toBe('30.00');
    expect(CouponRedemptionManager::shortfall($redemption))->toBe('claimed');

    $redemption->shortfall = 1000;
    expect(CouponRedemptionManager::shortfall($redemption))->toBe('10.00');
});

it('searches the code through the code table, and the note', function (): void {
    $searcher = new CouponSearcher();
    $manager = new Manager(Coupon::class);

    expect($searcher->get_sql_test($manager, ' summer '))->toBe([
        '`ecommerce_coupon`.`id` IN (SELECT `coupon_id` FROM ' .
        '`ecommerce_discount_code` WHERE `coupon_class` = ? AND `code` ' .
        'LIKE ?) OR `ecommerce_coupon`.`note` LIKE ?',
        Coupon::class,
        '%SUMMER%',
        '% summer %',
    ]);
});
