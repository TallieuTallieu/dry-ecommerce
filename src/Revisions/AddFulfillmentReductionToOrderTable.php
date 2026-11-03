<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * `ecommerce_order.fulfillment_reduction`: the part of `reduction` that came
 * off delivery, frozen at placement — a fixed coupon reaches delivery, and
 * delivery's tax was worked out on what was left of it. Cents; NULL on every
 * order placed before the column existed. See docs/orders.md.
 */
class AddFulfillmentReductionToOrderTable extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_order')
            ->alter(function (TableBuilder $table) {
                $table
                    ->addColumn('fulfillment_reduction', 'bigint')
                    ->length(20)
                    ->null();
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
                $table->dropColumn('fulfillment_reduction');
            });

        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Column fulfillment_reduction added to ecommerce_order';
    }

    public function describeDown(): string
    {
        return 'Column fulfillment_reduction dropped from ecommerce_order';
    }
}
