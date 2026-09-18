<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * The payment ledger: one append-only row per thing that happened to a
 * payment. Hardcoded on purpose — see docs/installation.md — and no
 * `updated`, because nothing updates an entry. See docs/payment.md.
 */
class CreatePaymentEntryTable extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_payment_entry')
            ->create(function (TableBuilder $table) {
                $table->addColumn('id', 'int')->length(11)->primaryKey();
                $table->addColumn('created', 'int')->length(11);

                // Empty only on an `unknown_payment` entry.
                $table->addColumn('order', 'int')->length(11)->null();

                $table->addColumn('provider', 'varchar')->length(64);
                $table->addColumn('payment_id', 'varchar')->length(255);
                $table->addColumn('kind', 'varchar')->length(32);
                $table->addColumn('status', 'varchar')->length(32)->null();

                // Cents, like every money column in this package.
                $table->addColumn('amount', 'bigint')->length(20)->null();

                // The provider's own id for a movement. NULL on the kinds
                // that move no money, and MySQL uniques ignore NULLs.
                $table->addColumn('reference', 'varchar')->length(255)->null();

                $table->addForeignKey('order', 'ecommerce_order', 'id');

                // What makes a replayed report write nothing.
                $table->addUnique(['provider', 'kind', 'reference']);

                // PaymentEntryRepository::forOrder() in ledger order. Leads
                // with `order`, so it stands in for the foreign key's index;
                // down() drops the table, which takes both with it.
                $table->addIndex(['order', 'id']);

                // PaymentEntryRepository::forPayment() — the webhook lookup.
                $table->addIndex(['provider', 'payment_id']);
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder->table('ecommerce_payment_entry')->drop();
        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Table ecommerce_payment_entry created';
    }

    public function describeDown(): string
    {
        return 'Table ecommerce_payment_entry dropped';
    }
}
