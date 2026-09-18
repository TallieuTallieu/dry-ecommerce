<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\QueryBuilder;
use Tnt\Dbi\TableBuilder;

/**
 * A line may hang off another line — a deposit under its crate, a returnable
 * container under what it contains. On both line tables, so the fact survives
 * the freeze at checkout instead of being reconstructed from options on every
 * basket render. See docs/cart.md.
 */
class AddParentToLineTables extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * @var array<string, string>
     */
    private const TABLES = [
        'ecommerce_cart_item' => 'ecommerce_cart_item',
        'ecommerce_order_item' => 'ecommerce_order_item',
    ];

    /**
     * ON DELETE SET NULL rather than CASCADE: InnoDB does not run cascades on
     * a self-referencing foreign key, so a CASCADE here would read as a
     * promise the database never keeps. Taking the children out with their
     * parent is the storage's job ({@see
     * \Tnt\Ecommerce\Cart\DatabaseCartStorage::removeItem()}); the constraint
     * is only here to stop a line pointing at a row that is gone.
     */
    private const ON_DELETE = 'SET NULL';

    /**
     * @return void
     */
    public function up(): void
    {
        foreach (self::TABLES as $name) {
            $this->queryBuilder
                ->table($name)
                ->alter(function (TableBuilder $table) use ($name) {
                    $table->addColumn('parent', 'int')->length(11)->null();

                    $table->addForeignKey(
                        'parent',
                        $name,
                        'id',
                        self::ON_DELETE
                    );
                });

            $this->execute();

            // One statement per builder: a QueryBuilder accumulates its query
            // text, so reusing it would concatenate the ALTERs.
            $this->queryBuilder = new QueryBuilder();
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        foreach (self::TABLES as $name) {
            $this->queryBuilder
                ->table($name)
                ->alter(function (TableBuilder $table) use ($name) {
                    $table->dropForeignKey('parent', $name, 'id');
                    $table->dropColumn('parent');
                });

            $this->execute();

            $this->queryBuilder = new QueryBuilder();
        }
    }

    public function describeUp(): string
    {
        return 'Column parent added to ecommerce_cart_item and ecommerce_order_item';
    }

    public function describeDown(): string
    {
        return 'Column parent dropped from ecommerce_cart_item and ecommerce_order_item';
    }
}
