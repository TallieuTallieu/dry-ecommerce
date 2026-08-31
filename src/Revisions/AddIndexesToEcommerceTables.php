<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\QueryBuilder;
use Tnt\Dbi\TableBuilder;

/**
 * The indexes behind the repository lookups — until now every one of these
 * filters was a full table scan. UNIQUE only where a duplicate is already a
 * bug on a table small enough to fix by hand (`ecommerce_stock.hid`); plain
 * everywhere else, so no shop's existing rows can strand the migration.
 */
class AddIndexesToEcommerceTables extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        // StockItemRepository::forBuyable() — once per buyable on listings
        // and cart validation. The FK index on `stock` alone selects nearly
        // the whole table (a shop has one or two stocks); the selective part
        // is the buyable. The `stock` prefix still serves forStock(). Plain,
        // not UNIQUE: one line per buyable per stock is what the code
        // assumes, but a shop with an accidental duplicate row must not have
        // its migration halt halfway.
        $this->queryBuilder
            ->table('ecommerce_stock_item')
            ->alter(function (TableBuilder $table) {
                $table->addIndex(['stock', 'item_class', 'item_id']);
            });

        $this->execute();

        // One statement per builder: a QueryBuilder accumulates its query
        // text, so reusing it would concatenate the ALTERs.
        $this->queryBuilder = new QueryBuilder();

        $this->queryBuilder
            ->table('ecommerce_order')
            ->alter(function (TableBuilder $table) {
                // OrderRepository::byPaymentId() — the payment-webhook
                // lookup. Plain: NullPayment and pre-payment orders share ''.
                $table->addIndex('payment_id');

                // OrderRepository::byOrderId() — the public reference behind
                // confirmation and track-my-order pages. Plain, not UNIQUE:
                // the reference is minted at placement, so every unplaced
                // draft holds '' and a second draft would collide.
                $table->addIndex('order_id');

                // OrderRepository::placed()/drafts() plus the ORDER BY
                // created DESC that init() adds. The reaper's drafts() +
                // updatedBefore() uses the `state` prefix. placed() spells
                // `state != 'draft'` — a range, so its sort still filesorts;
                // the equality lookups are what this index is for.
                $table->addIndex(['state', 'created']);

                // OrderRepository::forCustomer() — order history. The FK
                // index on `customer` finds the rows but not the created
                // DESC order; this serves both.
                $table->addIndex(['customer', 'created']);

                // An admin finding a guest's orders by the frozen email —
                // with no customer row behind a guest order, the order's own
                // copy is the only place to look.
                $table->addIndex('email');
            });

        $this->execute();
        $this->queryBuilder = new QueryBuilder();

        // DiscountCodeRepository::byCode() — every coupon submission. Plain,
        // not UNIQUE: existing shops may hold duplicate codes, and the
        // migration must not be the thing that finds out.
        $this->queryBuilder
            ->table('ecommerce_discount_code')
            ->alter(function (TableBuilder $table) {
                $table->addIndex('code');
            });

        $this->execute();
        $this->queryBuilder = new QueryBuilder();

        // CustomerRepository::byUser() — the address-book lookup — plus the
        // created DESC sort init() adds. Deliberately an index and not a
        // foreign key: the users table belongs to dry-accounts, which may
        // not be installed (see docs/customer.md).
        $this->queryBuilder
            ->table('ecommerce_customer')
            ->alter(function (TableBuilder $table) {
                $table->addIndex(['user', 'created']);
            });

        $this->execute();
        $this->queryBuilder = new QueryBuilder();

        // StockRepository::byHid() — how StockWorker addresses its stock.
        // UNIQUE as integrity rather than speed: a duplicate hid already
        // makes byHid() ambiguous, and the table holds a handful of
        // hand-made rows, so failing loudly here surfaces a real bug.
        $this->queryBuilder
            ->table('ecommerce_stock')
            ->alter(function (TableBuilder $table) {
                $table->addUnique('hid');
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder
            ->table('ecommerce_stock')
            ->alter(function (TableBuilder $table) {
                $table->dropUnique('hid');
            });

        $this->execute();

        // One statement per builder — see up().
        $this->queryBuilder = new QueryBuilder();

        $this->queryBuilder
            ->table('ecommerce_customer')
            ->alter(function (TableBuilder $table) {
                $table->dropIndex(['user', 'created']);
            });

        $this->execute();
        $this->queryBuilder = new QueryBuilder();

        $this->queryBuilder
            ->table('ecommerce_discount_code')
            ->alter(function (TableBuilder $table) {
                $table->dropIndex('code');
            });

        $this->execute();
        $this->queryBuilder = new QueryBuilder();

        $this->queryBuilder
            ->table('ecommerce_order')
            ->alter(function (TableBuilder $table) {
                $table->dropIndex('email');
                $table->dropIndex(['customer', 'created']);
                $table->dropIndex(['state', 'created']);
                $table->dropIndex('order_id');
                $table->dropIndex('payment_id');

                // When up() added idx_customer_created, InnoDB silently
                // dropped the implicit index behind the customer foreign key
                // as redundant — so the way back must hand the constraint an
                // index again in the same statement, under the implicit
                // index's own name, or MySQL refuses the drop above.
                $table
                    ->addIndex('customer')
                    ->identifier(
                        'fk_ecommerce_order_customer_ecommerce_customer_id'
                    );
            });

        $this->execute();
        $this->queryBuilder = new QueryBuilder();

        $this->queryBuilder
            ->table('ecommerce_stock_item')
            ->alter(function (TableBuilder $table) {
                $table->dropIndex(['stock', 'item_class', 'item_id']);

                // Same story as ecommerce_order.customer: the composite's
                // `stock` prefix took over the stock foreign key, so the
                // constraint needs its index back before the drop can pass.
                $table
                    ->addIndex('stock')
                    ->identifier(
                        'fk_ecommerce_stock_item_stock_ecommerce_stock_id'
                    );
            });

        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Indexes added behind the repository lookups on ' .
            'ecommerce_stock_item, ecommerce_order, ecommerce_discount_code, ' .
            'ecommerce_customer and ecommerce_stock';
    }

    public function describeDown(): string
    {
        return 'Indexes dropped from ecommerce_stock_item, ecommerce_order, ' .
            'ecommerce_discount_code, ecommerce_customer and ecommerce_stock';
    }
}
