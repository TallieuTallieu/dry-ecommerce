<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\QueryBuilder;
use Tnt\Dbi\TableBuilder;

/**
 * A slot for the choices a line was added with, on both line tables:
 * {@see \Tnt\Ecommerce\Cart\LineOptions} canonical JSON, or NULL. Sits last
 * in the migrator list on purpose — revisions are appended, never inserted.
 */
class AddOptionsToLineTables extends DatabaseRevision implements
    RevisionInterface
{
    private const TABLES = ['ecommerce_cart_item', 'ecommerce_order_item'];

    /**
     * The collation that makes `=` on this column compare bytes.
     *
     * The column is half the merge key: `CartItemRepository::forBuyable()`
     * finds a line by comparing it to the canonical string, and dry-dbi builds
     * tables `COLLATE utf8mb4_0900_ai_ci` — case-insensitive *and*
     * accent-insensitive. Under that collation MySQL reads
     * `{"cheese":"No Goat"}` as equal to `{"cheese":"no goat"}`, and `crème` as
     * equal to `creme`, so a second selection would merge into the first line
     * and be silently discarded — the first line's stored JSON wins. The column
     * holds canonical JSON, which is a byte string and not natural-language
     * text, so an exact collation is what it should have had.
     */
    private const COLLATION = 'utf8mb4_bin';

    /**
     * @return void
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->queryBuilder
                ->table($table)
                ->alter(function (TableBuilder $table) {
                    // `text` rather than `blob` so the column keeps a character
                    // set: a shop querying its orders with JSON_EXTRACT() needs
                    // one, and adminer shows text rather than a binary blob.
                    $table
                        ->addColumn('options', 'text')
                        ->collate(self::COLLATION)
                        ->null();
                });

            $this->execute();

            // One statement per builder: a QueryBuilder accumulates its query
            // text, so reusing it would concatenate the two ALTERs.
            $this->queryBuilder = new QueryBuilder();
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            $this->queryBuilder
                ->table($table)
                ->alter(function (TableBuilder $table) {
                    $table->dropColumn('options');
                });

            $this->execute();

            $this->queryBuilder = new QueryBuilder();
        }
    }

    public function describeUp(): string
    {
        return 'Column options added to ecommerce_cart_item and ecommerce_order_item';
    }

    public function describeDown(): string
    {
        return 'Column options dropped from ecommerce_cart_item and ecommerce_order_item';
    }
}
