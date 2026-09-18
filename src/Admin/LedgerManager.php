<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Admin;

use dry\admin\component\DateEdit;
use dry\admin\component\DateView;
use dry\admin\component\EnumView;
use dry\admin\component\StringEdit;
use dry\admin\component\StringView;
use dry\admin\Module;
use dry\orm\action\Edit;
use dry\orm\component\Pagination;
use dry\orm\component\Search;
use dry\orm\Index;
use dry\orm\Manager;
use dry\orm\paginate\Paginator;
use dry\orm\search\LikeSearcher;
use dry\orm\sort\StaticSorter;
use Tnt\Ecommerce\Model\PaymentEntry;
use Tnt\Ecommerce\Money;
use Tnt\Ecommerce\Payment\EntryKind;
use Tnt\Ecommerce\Payment\PaymentStatus;

/**
 * The payment ledger, flat and newest first. Read-only: the ledger is
 * append-only and {@see \Tnt\Ecommerce\Payment\PaymentLedger} is its one
 * writer, so there is no create, edit or delete here. See docs/admin.md.
 */
class LedgerManager extends Manager
{
    public function __construct()
    {
        parent::__construct(PaymentEntry::class, [
            'id' => 'ledger',
            'title' => 'Ledger',
            'singular' => 'ledger entry',
            'plural' => 'ledger entries',
            'icon' => Module::ICON_RECEIPT,
            'intro' =>
                'Every payment ledger entry, newest first. Read-only: the ledger is written by the shop, never by hand.',
        ]);

        // Readonly: view-only popup, and dry's save API refuses a write.
        $this->actions[] = $view = new Edit(
            [
                DateEdit::create('created')
                    ->set_label('Created')
                    ->set_unix_timestamp(true),
                StringEdit::create('order')->set_label('Order id'),
                StringEdit::create('provider')->set_label('Provider'),
                StringEdit::create('payment_id')->set_label('Payment id'),
                StringEdit::create('kind')->set_label('Kind'),
                StringEdit::create('status')->set_label('Status'),
                StringEdit::create('amount')->set_label('Amount (cents)'),
                StringEdit::create('reference')->set_label('Reference'),
            ],
            [
                'popup' => true,
                'readonly' => true,
            ]
        );

        $this->header[] = new Search();
        $this->footer[] = new Pagination();

        $this->index = Index::create()
            ->add_component(
                DateView::create('created', [
                    'header' => 'Created',
                    'format' => 'd/m/Y H:i:s',
                ])
            )
            ->add_component(
                StringView::create('order', [
                    'header' => 'Order',
                    'value' => self::order(...),
                ])
            )
            ->add_component(
                StringView::create('provider', ['header' => 'Provider'])
            )
            ->add_component(
                StringView::create('payment_id', [
                    'header' => 'Payment id',
                ])->set_copyable()
            )
            ->add_component(
                EnumView::create('kind', self::labels(EntryKind::cases()))
            )
            ->add_component(
                EnumView::create('status', self::labels(PaymentStatus::cases()))
            )
            ->add_component(
                StringView::create('amount', [
                    'header' => 'Amount',
                    'value' => self::amount(...),
                ])
            )
            ->add_component(
                StringView::create('reference', ['header' => 'Reference'])
            )
            ->add_component($view->create_link())
            ->set_row_action($view->create_link(''))
            ->set_searcher(
                new LikeSearcher([
                    'payment_id',
                    'reference',
                    'order',
                    ['order', 'order_id'],
                ])
            )
            ->set_sorter(new StaticSorter('created', StaticSorter::DESC))
            ->set_paginator(new Paginator(25));
    }

    /**
     * The order's row id, and its public reference once it has one.
     *
     * @param PaymentEntry $entry
     * @return string
     */
    public static function order(PaymentEntry $entry): string
    {
        $order = $entry->order;

        if ($order === null) {
            return '';
        }

        $reference = $order->order_id;

        return $reference === null || $reference === ''
            ? (string) $order->id
            : $order->id . ' · ' . $reference;
    }

    /**
     * The amount in units, or nothing on an entry that moves no money.
     *
     * @param PaymentEntry $entry
     * @return string
     */
    public static function amount(PaymentEntry $entry): string
    {
        return $entry->amount === null ? '' : Money::toDecimal($entry->amount);
    }

    /**
     * EnumView's `[value, label]` pairs for a backed enum.
     *
     * @param array<int, \BackedEnum> $cases
     * @return list<array{string, string}>
     */
    private static function labels(array $cases): array
    {
        return array_map(
            static fn(\BackedEnum $case): array => [
                (string) $case->value,
                ucfirst(str_replace('_', ' ', (string) $case->value)),
            ],
            array_values($cases)
        );
    }
}
