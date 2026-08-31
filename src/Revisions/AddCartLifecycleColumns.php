<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * The cart's afterlife, in four columns: `order` (the cart→order link, kept
 * for provenance), `token` (what a cookie may hold — never the row id),
 * `deleted` (soft-delete, written by the Paid listener) and
 * `fulfillment_attributes` (the visitor's answers, off the session). Sits at
 * the end of the migrator list on purpose: revisions are appended, never
 * inserted. See docs/cart.md.
 */
class AddCartLifecycleColumns extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_cart')
            ->alter(function (TableBuilder $table) {
                $table->addColumn('order', 'int')->length(11)->null();
                $table->addColumn('token', 'varchar')->length(64)->null();
                $table->addColumn('deleted', 'int')->length(11)->null();
                $table->addColumn('fulfillment_attributes', 'text')->null();

                // SET NULL, not CASCADE: reaping an order must not take a
                // living basket with it — the link is provenance, not custody.
                $table->addForeignKey(
                    'order',
                    'ecommerce_order',
                    'id',
                    'SET NULL'
                );

                // Unique so a token names at most one cart; nullable because
                // every cart from before this revision has none.
                $table->addUnique('token');
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder
            ->table('ecommerce_cart')
            ->alter(function (TableBuilder $table) {
                $table->dropForeignKey('order', 'ecommerce_order');
                $table->dropUnique('token');
                $table->dropColumn('order');
                $table->dropColumn('token');
                $table->dropColumn('deleted');
                $table->dropColumn('fulfillment_attributes');
            });

        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Columns order, token, deleted and fulfillment_attributes ' .
            'added to ecommerce_cart';
    }

    public function describeDown(): string
    {
        return 'Columns order, token, deleted and fulfillment_attributes ' .
            'dropped from ecommerce_cart';
    }
}
