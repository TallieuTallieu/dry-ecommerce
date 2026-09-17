<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\QueryBuilder;
use Tnt\Dbi\TableBuilder;

/**
 * One row per go at paying an order, and the key a gateway hands its provider
 * so a double-submit is answered with one payment rather than two.
 *
 * `ecommerce_order.payment_id` still names the attempt the order is currently
 * on — this table is what the superseded ones fall into instead of belonging
 * to no order at all. See docs/payment.md.
 */
class CreatePaymentAttemptTable extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_payment_attempt')
            ->create(function (TableBuilder $table) {
                $table->addColumn('id', 'int')->length(11)->primaryKey();
                $table->addColumn('created', 'int')->length(11);
                $table->addColumn('updated', 'int')->length(11);

                $table->addColumn('order', 'int')->length(11);

                // The provider's own id for this attempt.
                $table->addColumn('payment_id', 'varchar')->length(255);

                // Where this attempt got to — see
                // Tnt\Ecommerce\Payment\PaymentStatus.
                $table->addColumn('status', 'varchar')->length(255);

                // The order's payment key as it stood when the attempt was
                // made. Two attempts under one key is the double-submit the
                // key exists to let a provider collapse.
                $table->addColumn('payment_key', 'varchar')->length(255);

                $table->addForeignKey('order', 'ecommerce_order', 'id');

                // The webhook lookup: a provider announces an id, and this
                // is how the attempt behind it is found. Plain, not UNIQUE —
                // a provider that reuses an id across its own retries must
                // not halt the migration.
                $table->addIndex('payment_id');
            });

        $this->execute();

        // One statement per builder: a QueryBuilder accumulates its query
        // text, so reusing it would concatenate the two statements.
        $this->queryBuilder = new QueryBuilder();

        $this->queryBuilder
            ->table('ecommerce_order')
            ->alter(function (TableBuilder $table) {
                // Re-minted on every placement, unlike `order_id`, which a
                // re-placed order deliberately keeps: a gateway that sent the
                // old key would be handed back the payment the customer
                // already abandoned.
                $table
                    ->addColumn('payment_key', 'varchar')
                    ->length(255)
                    ->default('');
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
                $table->dropColumn('payment_key');
            });

        $this->execute();
        $this->queryBuilder = new QueryBuilder();

        $this->queryBuilder->table('ecommerce_payment_attempt')->drop();
        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Table ecommerce_payment_attempt created, and column ' .
            'payment_key added to ecommerce_order';
    }

    public function describeDown(): string
    {
        return 'Table ecommerce_payment_attempt dropped, and column ' .
            'payment_key dropped from ecommerce_order';
    }
}
