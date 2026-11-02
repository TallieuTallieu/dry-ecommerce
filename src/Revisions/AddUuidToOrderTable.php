<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * `ecommerce_order.uuid`, the key a URL names an order by
 * ({@see \Tnt\Ecommerce\Order\OrderUuid}). Added empty: `Order::save()`
 * fills it on a new row, and existing rows get theirs from
 * `php oak ecommerce:fill-order-uuids`
 * ({@see \Tnt\Ecommerce\Console\FillOrderUuidsCommand}). Nullable for both,
 * and for a row a seeder writes with raw SQL — MySQL's unique allows any
 * number of NULLs.
 */
class AddUuidToOrderTable extends DatabaseRevision implements RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_order')
            ->alter(function (TableBuilder $table) {
                $table->addColumn('uuid', 'char')->length(36)->null();
                $table->addUnique('uuid');
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
                $table->dropUnique('uuid');
                $table->dropColumn('uuid');
            });

        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Column uuid added to ecommerce_order, unique';
    }

    public function describeDown(): string
    {
        return 'Column uuid dropped from ecommerce_order';
    }
}
