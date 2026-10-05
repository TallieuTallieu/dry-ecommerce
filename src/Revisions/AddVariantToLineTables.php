<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\QueryBuilder;
use Tnt\Dbi\TableBuilder;

/**
 * The variant a line holds, in columns of its own rather than in the options
 * JSON: the id on both line tables, and on the order line the title it was
 * sold under. See docs/variants.md.
 */
class AddVariantToLineTables extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * `variant` is half the cart's merge key, compared with `=` — the same
     * reason {@see AddOptionsToLineTables} gives for its collation: under
     * dry-dbi's case- and accent-insensitive default, variant `m` would merge
     * into variant `M`.
     */
    private const ID_COLLATION = 'utf8mb4_bin';

    /**
     * The cart line only references the variant; the order line keeps its
     * own copy of the title, so a renamed or deleted variant does not change
     * what an old order says was sold. The unit price needs no column: it is
     * `price / quantity`, exactly.
     *
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_cart_item')
            ->alter(function (TableBuilder $table) {
                $table
                    ->addColumn('variant', 'varchar')
                    ->length(64)
                    ->collate(self::ID_COLLATION)
                    ->null();
            });

        $this->execute();

        // One statement per builder: a QueryBuilder accumulates its query
        // text, so reusing it would concatenate the ALTERs.
        $this->queryBuilder = new QueryBuilder();

        $this->queryBuilder
            ->table('ecommerce_order_item')
            ->alter(function (TableBuilder $table) {
                $table
                    ->addColumn('variant', 'varchar')
                    ->length(64)
                    ->collate(self::ID_COLLATION)
                    ->null();

                $table
                    ->addColumn('variant_title', 'varchar')
                    ->length(255)
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
            ->table('ecommerce_cart_item')
            ->alter(function (TableBuilder $table) {
                $table->dropColumn('variant');
            });

        $this->execute();

        $this->queryBuilder = new QueryBuilder();

        $this->queryBuilder
            ->table('ecommerce_order_item')
            ->alter(function (TableBuilder $table) {
                $table->dropColumn('variant');
                $table->dropColumn('variant_title');
            });

        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Column variant added to ecommerce_cart_item, and variant and ' .
            'variant_title to ecommerce_order_item';
    }

    public function describeDown(): string
    {
        return 'Column variant dropped from ecommerce_cart_item, and variant ' .
            'and variant_title from ecommerce_order_item';
    }
}
