<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * The package's own coupon, {@see \Tnt\Ecommerce\Model\Coupon}. Its code is
 * not here: it lives in `ecommerce_discount_code`, like every coupon's. See
 * docs/discounts.md#the-shipped-coupon.
 */
class CreateCouponTable extends DatabaseRevision implements RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_coupon')
            ->create(function (TableBuilder $table) {
                $table->addColumn('id', 'int')->length(11)->primaryKey();
                $table->addColumn('created', 'int')->length(11);
                $table->addColumn('updated', 'int')->length(11);
                $table->addColumn('type', 'varchar')->length(16);

                // Cents for a fixed coupon, hundredths of a percent for a
                // percentage one (750 is 7.5%) — bigint either way.
                $table->addColumn('value', 'bigint')->length(20);

                // Cents; a fixed coupon's only. Written by redeem() in one
                // statement, never by a save of the row.
                $table->addColumn('balance', 'bigint')->length(20)->null();

                // Unix times; NULL is no start and no end.
                $table->addColumn('starts', 'int')->length(11)->null();
                $table->addColumn('ends', 'int')->length(11)->null();

                $table->addColumn('minimum', 'bigint')->length(20)->null();

                // NULL is unlimited.
                $table->addColumn('max_uses', 'int')->length(11)->null();
                $table->addColumn('uses', 'int')->length(11)->default(0);

                $table->addColumn('note', 'text')->null();
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder->table('ecommerce_coupon')->drop();
        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Table ecommerce_coupon created';
    }

    public function describeDown(): string
    {
        return 'Table ecommerce_coupon dropped';
    }
}
