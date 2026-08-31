<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * A guest order has no customer row: identity and addresses live on the
 * order's own frozen columns, and the row is only account continuity. The
 * foreign key stays — a non-null value must still name a real customer. Sits
 * at the end of the migrator list on purpose: revisions are appended, never
 * inserted. See docs/customer.md.
 */
class MakeOrderCustomerNullable extends DatabaseRevision implements
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
                    ->changeColumn('customer')
                    ->type('int')
                    ->length(11)
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
                $table->changeColumn('customer')->type('int')->length(11);
            });

        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Column customer on ecommerce_order made nullable';
    }

    public function describeDown(): string
    {
        return 'Column customer on ecommerce_order made NOT NULL again';
    }
}
