<?php

declare(strict_types=1);

/*
 * The coupon screens, as far as they can be checked without a booted dry
 * admin: what the managers are made of and what their columns show — as
 * LedgerAdminTest does for the ledger.
 */

use dry\admin\component\Conditional;
use dry\admin\component\DateEdit;
use dry\admin\component\EnumEdit;
use dry\admin\component\EnumSwitcher;
use dry\admin\component\IntegerEdit;
use dry\admin\component\Stack;
use dry\admin\component\StringEdit;
use dry\api\ConvertableToApiException;
use dry\orm\action\Create;
use dry\orm\action\Delete;
use dry\orm\action\DeleteAll;
use dry\orm\action\Edit;
use dry\orm\action\MultiDelete;
use dry\orm\Manager;
use Tnt\Ecommerce\Admin\CouponManager;
use Tnt\Ecommerce\Admin\CouponRedemptionManager;
use Tnt\Ecommerce\Admin\CouponSearcher;
use Tnt\Ecommerce\CouponCodeRefused;
use Tnt\Ecommerce\CouponTakesNothing;
use Tnt\Ecommerce\Discount\CouponType;
use Tnt\Ecommerce\Model\Coupon;
use Tnt\Ecommerce\Model\CouponRedemption;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\NotACouponAmount;
use Tnt\Ecommerce\NotAnAmount;
use Tnt\Ecommerce\NotAPercentage;

/**
 * An EnumSwitcher tab's key and the fields it asks, through any Stack.
 *
 * @param array<int, mixed> $option
 * @return array{mixed, list<string>}
 */
function tabFields(array $option): array
{
    /** @var list<dry\admin\component\Component> $components */
    $components = $option[2];

    return [$option[0], fieldsOf($components)];
}

/**
 * The fields every branch of a Conditional asks.
 *
 * @return list<string>
 */
function conditionalFields(Conditional $conditional): array
{
    return fieldsOf(array_merge(...array_column($conditional->branches, 1)));
}

/**
 * @param array<int, dry\admin\component\Component> $components
 * @return list<string>
 */
function fieldsOf(array $components): array
{
    $fields = [];

    foreach ($components as $c) {
        if ($c instanceof Stack) {
            /** @var array<int, dry\admin\component\Component> $children */
            $children = $c->components;
            $fields = [...$fields, ...fieldsOf($children)];
        } elseif ($c instanceof StringEdit || $c instanceof IntegerEdit) {
            $fields[] = $c->field;
        }
    }

    return $fields;
}

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

it('shows a new coupon\'s value fields from the start', function (): void {
    // dry's Create loads no row, so a Conditional there never shows a branch,
    // and an EnumDisplay waits for its select to change: the value fields
    // went missing and every save was refused. EnumSwitcher shows its first
    // tab's fields as it is drawn.
    $create = (new CouponManager())->actions[0];
    assert($create instanceof Create);

    foreach ($create->components as $component) {
        expect($component)->not->toBeInstanceOf(Conditional::class);
        expect($component)->not->toBeInstanceOf(EnumEdit::class);
    }

    $type = $create->components[0];
    assert($type instanceof EnumSwitcher);
    expect($type->field)->toBe('type');

    expect(array_map(tabFields(...), $type->values))->toBe([
        [CouponType::Percentage->value, ['percent', 'max_uses']],
        // The balance is a fixed coupon's limit: no use limit to set.
        [CouponType::Fixed->value, ['amount']],
    ]);
});

it('asks a use limit of percentage coupons only, on edit', function (): void {
    $edit = (new CouponManager())->actions[1];
    assert($edit instanceof Edit);

    $limits = array_values(
        array_filter(
            $edit->components,
            static fn(mixed $c): bool => $c instanceof Conditional &&
                in_array('max_uses', conditionalFields($c), true)
        )
    );

    expect($limits)->toHaveCount(1);
    expect($limits[0]->default)->toBe([]);
    expect($limits[0]->branches)->toHaveCount(1);

    $test = $limits[0]->branches[0][0];
    expect($test->evaluate(Coupon::percentage('5')))->toBeTrue();
    expect($test->evaluate(Coupon::fixed(1000)))->toBeFalse();

    foreach ($edit->components as $component) {
        expect(
            $component instanceof IntegerEdit ? $component->field : ''
        )->not->toBe('max_uses');
    }
});

it('lets a coupon\'s dates be empty, on create and edit', function (): void {
    // Without nullable, dry shows a coupon without dates as 01/01/1970 and
    // saves that back.
    foreach ((new CouponManager())->actions as $action) {
        assert($action instanceof Create); // Edit extends Create

        $dates = [];
        foreach ($action->components as $c) {
            if ($c instanceof Stack) {
                foreach ($c->components as $child) {
                    if ($child instanceof DateEdit) {
                        $dates[$child->field] = $child->nullable;
                    }
                }
            }
        }

        expect($dates)->toBe(['starts' => true, 'ends' => true]);
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

it('turns every save refusal into an API error on its field', function (
    ConvertableToApiException $refusal,
    string $code,
    string $field
): void {
    // dry's Router answers anything else as a bare php.exception: the admin
    // would show no reason, and no field.
    $error = $refusal->to_api_exception();

    expect($error)->toBeInstanceOf(dry\api\Exception::class);
    expect($error->code)->toBe($code);
    expect($error->data)->toBe(['field' => $field]);
})->with([
    'taken' => [
        CouponCodeRefused::taken('SUMMER10'),
        'ecommerce.coupon_code_taken',
        'code',
    ],
    'locked' => [
        CouponCodeRefused::locked('SUMMER10'),
        'ecommerce.coupon_code_locked',
        'code',
    ],
    'unreadable percentage' => [
        NotAPercentage::unreadable('abc'),
        'ecommerce.coupon_percentage_invalid',
        'percent',
    ],
    'percentage out of range' => [
        NotAPercentage::outOfRange('101'),
        'ecommerce.coupon_percentage_invalid',
        'percent',
    ],
    'fixed of nothing' => [
        CouponTakesNothing::of(CouponType::Fixed),
        'ecommerce.coupon_value_invalid',
        'amount',
    ],
    'percentage of nothing' => [
        CouponTakesNothing::of(CouponType::Percentage),
        'ecommerce.coupon_value_invalid',
        'percent',
    ],
    'unreadable amount' => [
        NotACouponAmount::in('amount', NotAnAmount::unreadable('€12')),
        'ecommerce.coupon_amount_invalid',
        'amount',
    ],
    'unreadable minimum' => [
        NotACouponAmount::in(
            'minimum_amount',
            NotAnAmount::unreadable('1.250,00')
        ),
        'ecommerce.coupon_amount_invalid',
        'minimum_amount',
    ],
]);
