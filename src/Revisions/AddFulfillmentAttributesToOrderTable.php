<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * The order's own copy of the fulfillment attributes it was placed with — a
 * JSON object of the method's required attributes, or NULL. Sits at the end
 * of the migrator list on purpose: revisions are appended, never inserted.
 */
class AddFulfillmentAttributesToOrderTable extends DatabaseRevision implements
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
                $table->addColumn('fulfillment_attributes', 'text')->null();
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
                $table->dropColumn('fulfillment_attributes');
            });

        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Column fulfillment_attributes added to ecommerce_order';
    }

    public function describeDown(): string
    {
        return 'Column fulfillment_attributes dropped from ecommerce_order';
    }
}
