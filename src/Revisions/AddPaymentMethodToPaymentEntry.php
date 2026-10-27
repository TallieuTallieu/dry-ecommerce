<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * How the money on a `captured` entry was paid, as the provider reported it:
 * the method and, for a card, its brand. See docs/payment.md.
 */
class AddPaymentMethodToPaymentEntry extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * NULL on every kind but `captured`, on a capture whose report named no
     * method, and on every entry written before this revision — no backfill.
     *
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_payment_entry')
            ->alter(function (TableBuilder $table) {
                $table->addColumn('method', 'varchar')->length(255)->null();
                $table->addColumn('card_label', 'varchar')->length(255)->null();
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder
            ->table('ecommerce_payment_entry')
            ->alter(function (TableBuilder $table) {
                $table->dropColumn('method');
                $table->dropColumn('card_label');
            });

        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Columns method and card_label added to ecommerce_payment_entry';
    }

    public function describeDown(): string
    {
        return 'Columns method and card_label dropped from ecommerce_payment_entry';
    }
}
