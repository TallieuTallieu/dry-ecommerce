<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * NULL is "not filled in yet": a draft is written progressively and a guest
 * freezes no identity, so every column placement writes goes nullable. Until
 * now a partial insert leaned on MySQL's loose sql_mode inventing '' — NULL
 * says there is no value, and is legal under strict mode too. Sits at the end
 * of the migrator list on purpose: revisions are appended, never inserted.
 * See docs/orders.md.
 */
class MakeOrderPlacementColumnsNullable extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * Pinned history, like every revision: these lists never change, even if
     * the model grows or loses columns later.
     */
    private const VARCHAR_COLUMNS = [
        'order_id',
        'payment_id',
        'prices',
        'payment_status',
        'first_name',
        'last_name',
        'email',
        'company',
        'vat',
        'billing_street',
        'billing_number',
        'billing_postal_code',
        'billing_city',
        'billing_country',
        'shipping_street',
        'shipping_number',
        'shipping_postal_code',
        'shipping_city',
        'shipping_country',
    ];

    private const MONEY_COLUMNS = [
        'total',
        'subtotal',
        'reduction',
        'fulfillment_cost',
        'tax',
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_order')
            ->alter(function (TableBuilder $table) {
                foreach (self::VARCHAR_COLUMNS as $column) {
                    $table
                        ->changeColumn($column)
                        ->type('varchar')
                        ->length(255)
                        ->null();
                }

                foreach (self::MONEY_COLUMNS as $column) {
                    $table
                        ->changeColumn($column)
                        ->type('bigint')
                        ->length(20)
                        ->null();
                }
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder
            ->table('ecommerce_order')
            ->alter(function (TableBuilder $table) {
                foreach (self::VARCHAR_COLUMNS as $column) {
                    $table->changeColumn($column)->type('varchar')->length(255);
                }

                foreach (self::MONEY_COLUMNS as $column) {
                    $table->changeColumn($column)->type('bigint')->length(20);
                }
            });

        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Columns placement writes on ecommerce_order made nullable';
    }

    public function describeDown(): string
    {
        return 'Columns placement writes on ecommerce_order made NOT NULL ' .
            'again';
    }
}
