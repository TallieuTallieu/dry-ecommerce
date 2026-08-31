<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use dry\db\Connection;
use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * Where an order stands in its own lifecycle: `draft` or `placed`
 * ({@see \Tnt\Ecommerce\Order\OrderState}). Existing rows are backfilled
 * `placed` — every pre-state order was a real one. Sits at the end of the
 * migrator list on purpose: revisions are appended, never inserted.
 */
class AddOrderStateColumn extends DatabaseRevision implements RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_order')
            ->alter(function (TableBuilder $table) {
                $table->addColumn('state', 'varchar')->length(255)->default('');
            });

        $this->execute();

        // Every order that exists before the column does was placed for real
        // — drafts only start existing after this revision has run.
        $this->run("UPDATE `ecommerce_order` SET `state` = 'placed'");
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder
            ->table('ecommerce_order')
            ->alter(function (TableBuilder $table) {
                $table->dropColumn('state');
            });

        $this->execute();
    }

    /**
     * One raw statement — the builder speaks DDL and SELECT, not UPDATE. A
     * seam like {@see DatabaseRevision::execute()}, for the same reason.
     *
     * @param string $sql
     * @return void
     */
    protected function run(string $sql): void
    {
        Connection::get()->query($sql);
    }

    public function describeUp(): string
    {
        return 'Column state added to ecommerce_order, existing rows placed';
    }

    public function describeDown(): string
    {
        return 'Column state dropped from ecommerce_order';
    }
}
