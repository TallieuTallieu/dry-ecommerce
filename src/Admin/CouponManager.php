<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Admin;

use dry\admin\component\Conditional;
use dry\admin\component\DateEdit;
use dry\admin\component\EnumEdit;
use dry\admin\component\EnumView;
use dry\admin\component\IntegerEdit;
use dry\admin\component\StringEdit;
use dry\admin\component\StringView;
use dry\admin\Module;
use dry\expr\Expr;
use dry\orm\action\Create;
use dry\orm\action\Edit;
use dry\orm\action\SubPage;
use dry\orm\component\InlineManager;
use dry\orm\component\Pagination;
use dry\orm\component\Search;
use dry\orm\Index;
use dry\orm\Manager;
use dry\orm\paginate\Paginator;
use dry\orm\sort\StaticSorter;
use Tnt\Ecommerce\Discount\CouponType;
use Tnt\Ecommerce\Model\Coupon;
use Tnt\Ecommerce\Money;

/**
 * The package's coupons: create one with a generated code, edit its dates,
 * limits and note, read its balance, uses and redemptions. Nothing registers
 * it — a project adds `new CouponManager()` to a portal of its own. No delete:
 * a coupon an order was placed with stays. See docs/admin.md#coupons.
 */
class CouponManager extends Manager
{
    public function __construct()
    {
        parent::__construct(Coupon::class, [
            'id' => 'coupons',
            'title' => 'Coupons',
            'singular' => 'coupon',
            'plural' => 'coupons',
            'icon' => Module::ICON_CURRENCY,
            'intro' =>
                'A percentage off the products, or a fixed amount off the products and delivery until it is spent. Leave the code empty to have one generated.',
        ]);

        $isPercentage = Expr::eq(
            Expr::field('type'),
            Expr::lit(CouponType::Percentage->value)
        );
        $isFixed = Expr::eq(
            Expr::field('type'),
            Expr::lit(CouponType::Fixed->value)
        );
        $isUsed = Expr::gt(Expr::field('uses'), Expr::lit(0));

        $this->actions[] = $create = new Create(
            [
                EnumEdit::create('type', self::types())
                    ->set_label('Type')
                    ->set_required(),
                Conditional::create()
                    ->add($isPercentage, [
                        StringEdit::create('percent')
                            ->set_label('Percentage')
                            ->set_tooltip(
                                '7,5 for 7.5% — at most two decimals.'
                            )
                            ->set_required(),
                    ])
                    ->add($isFixed, [
                        StringEdit::create('amount')
                            ->set_label('Amount (€)')
                            ->set_required(),
                    ]),
                StringEdit::create('code')
                    ->set_label('Code')
                    ->set_tooltip('Leave empty to generate one.'),
                ...self::limits(),
                Conditional::create()
                    ->add($isPercentage, [
                        IntegerEdit::create('max_uses')
                            ->set_label('Maximum uses')
                            ->set_min_value(0)
                            ->set_default_value(1),
                    ])
                    ->add($isFixed, [self::maxUses()]),
                self::note(),
            ],
            ['popup' => true]
        );

        $this->actions[] = $edit = new Edit(
            [
                EnumEdit::create('type', self::types())
                    ->set_label('Type')
                    ->set_disabled(),
                Conditional::create()
                    ->add($isPercentage, [
                        StringEdit::create('percent')
                            ->set_label('Percentage')
                            ->set_required(),
                    ])
                    ->add($isFixed, [
                        // The balance is what is left of this; changing it
                        // here would not change the balance.
                        StringEdit::create('amount')
                            ->set_label('Amount (€)')
                            ->set_disabled(),
                        StringEdit::create('balance_amount')
                            ->set_label('Balance (€)')
                            ->set_disabled(),
                    ]),
                Conditional::create()
                    ->add($isUsed, [
                        StringEdit::create('code')
                            ->set_label('Code')
                            ->set_tooltip(
                                'Used, so a customer may hold this code: it can no longer change.'
                            )
                            ->set_disabled(),
                    ])
                    ->set_default([
                        StringEdit::create('code')->set_label('Code'),
                    ]),
                ...self::limits(),
                self::maxUses(),
                IntegerEdit::create('uses')->set_label('Uses')->set_disabled(),
                self::note(),
            ],
            ['nav_label' => 'General']
        );

        $this->add_subpage(
            (new SubPage('redemptions', 'Redemptions', [
                InlineManager::create(new CouponRedemptionManager())
                    ->set_foreign_key('coupon')
                    ->set_title('Redemptions'),
            ]))->set_save_button(false)
        );

        $this->header[] = $create->create_link('Add coupon');
        $this->header[] = new Search();
        $this->footer[] = new Pagination();

        $this->index = Index::create()
            ->add_component(StringView::create('code', ['header' => 'Code']))
            ->add_component(EnumView::create('type', self::types()))
            ->add_component(
                StringView::create('value', [
                    'header' => 'Value',
                    'value' => self::value(...),
                ])
            )
            ->add_component(
                StringView::create('balance', [
                    'header' => 'Balance',
                    'value' => self::balance(...),
                ])
            )
            ->add_component(
                StringView::create('uses', [
                    'header' => 'Uses',
                    'value' => self::uses(...),
                ])
            )
            ->add_component(
                StringView::create('ends', [
                    'header' => 'Valid',
                    'value' => self::validity(...),
                ])
            )
            ->add_component(StringView::create('note', ['header' => 'Note']))
            ->add_component($edit->create_link())
            ->set_row_action($edit->create_link(''))
            ->set_searcher(new CouponSearcher())
            ->set_sorter(new StaticSorter('created', StaticSorter::DESC))
            ->set_paginator(new Paginator(25));
    }

    /**
     * `7.5%`, or `12.50` for a fixed coupon.
     *
     * @param Coupon $coupon
     * @return string
     */
    public static function value(Coupon $coupon): string
    {
        return $coupon->type === CouponType::Percentage->value
            ? $coupon->getPercentageLabel() . '%'
            : Money::toDecimal($coupon->getValue());
    }

    /**
     * What is left on a fixed coupon; nothing for a percentage.
     *
     * @param Coupon $coupon
     * @return string
     */
    public static function balance(Coupon $coupon): string
    {
        return $coupon->type === CouponType::Fixed->value
            ? Money::toDecimal($coupon->getBalance())
            : '';
    }

    /**
     * `1 / 1`, or the count alone when uses are unlimited.
     *
     * @param Coupon $coupon
     * @return string
     */
    public static function uses(Coupon $coupon): string
    {
        $max = $coupon->getMaxUses();

        return $coupon->getUses() . ($max === null ? '' : ' / ' . $max);
    }

    /**
     * The days the coupon applies on, or `always`.
     *
     * @param Coupon $coupon
     * @return string
     */
    public static function validity(Coupon $coupon): string
    {
        $starts = $coupon->getStarts();
        $ends = $coupon->getEnds();

        if ($starts === null && $ends === null) {
            return 'always';
        }

        if ($ends === null) {
            return 'from ' . date('d/m/Y', (int) $starts);
        }

        if ($starts === null) {
            return 'until ' . date('d/m/Y', $ends);
        }

        return date('d/m/Y', $starts) . ' – ' . date('d/m/Y', $ends);
    }

    /**
     * EnumEdit's and EnumView's `[value, label]` pairs.
     *
     * @return list<array{string, string}>
     */
    private static function types(): array
    {
        return [
            [CouponType::Percentage->value, 'Percentage'],
            [CouponType::Fixed->value, 'Fixed amount'],
        ];
    }

    /**
     * The dates and the minimum, the same on create and edit.
     *
     * @return list<\dry\admin\component\Component>
     */
    private static function limits(): array
    {
        return [
            DateEdit::create('starts')
                ->set_label('Valid from')
                ->set_unix_timestamp(true),
            DateEdit::create('ends')
                ->set_label('Valid until (inclusive)')
                ->set_unix_timestamp(true),
            StringEdit::create('minimum_amount')
                ->set_label('Minimum order (€)')
                ->set_tooltip(
                    'Held against the products that take a discount, before delivery. Empty: no minimum.'
                ),
        ];
    }

    /**
     * @return IntegerEdit
     */
    private static function maxUses(): IntegerEdit
    {
        return IntegerEdit::create('max_uses')
            ->set_label('Maximum uses')
            ->set_min_value(0)
            ->set_description('Empty or 0: unlimited.');
    }

    /**
     * @return StringEdit
     */
    private static function note(): StringEdit
    {
        return StringEdit::create('note')->set_label('Note')->set_multiline();
    }
}
