<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * One row per coupon spent on a paid order,
 * {@see \Tnt\Ecommerce\Model\CouponRedemption}. UNIQUE(order) is the claim:
 * a second Paid for the same order fails to insert and redeems nothing. See
 * docs/discounts.md#redemption.
 */
class CreateCouponRedemptionTable extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_coupon_redemption')
            ->create(function (TableBuilder $table) {
                $table->addColumn('id', 'int')->length(11)->primaryKey();
                $table->addColumn('created', 'int')->length(11);
                $table->addColumn('coupon', 'int')->length(11);
                $table->addColumn('order', 'int')->length(11);

                // Cents: what the order took off, and what the coupon could
                // not cover — NULL while claimed but not settled.
                $table->addColumn('amount', 'bigint')->length(20);
                $table->addColumn('shortfall', 'bigint')->length(20)->null();

                $table->addForeignKey('coupon', 'ecommerce_coupon', 'id');
                $table->addForeignKey('order', 'ecommerce_order', 'id');

                $table->addUnique('order');

                // CouponRedemptionRepository::forCoupon() in order; leads
                // with `coupon`, so it stands in for the foreign key's index.
                $table->addIndex(['coupon', 'id']);
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder->table('ecommerce_coupon_redemption')->drop();
        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Table ecommerce_coupon_redemption created';
    }

    public function describeDown(): string
    {
        return 'Table ecommerce_coupon_redemption dropped';
    }
}
