<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * The tax rate each order line was sold at, frozen beside its price: a rate
 * changed on the product later does not restate an old invoice. See
 * docs/tax.md.
 */
class AddVatPercentageToOrderItem extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * DECIMAL(7,4): TaxRateInterface honours a rate to four decimal places of
     * a percent, up to 999.9999. NULL for a line whose buyable carries no tax,
     * and for every line placed before this revision.
     *
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_order_item')
            ->alter(function (TableBuilder $table) {
                $table
                    ->addColumn('vat_percentage', 'decimal')
                    ->length('7,4')
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
            ->table('ecommerce_order_item')
            ->alter(function (TableBuilder $table) {
                $table->dropColumn('vat_percentage');
            });

        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Column vat_percentage added to ecommerce_order_item';
    }

    public function describeDown(): string
    {
        return 'Column vat_percentage dropped from ecommerce_order_item';
    }
}
