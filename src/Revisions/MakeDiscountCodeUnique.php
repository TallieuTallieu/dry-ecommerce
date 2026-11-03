<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * Codes are stored trimmed and uppercase, and are unique: existing rows are
 * normalised first, then UNIQUE(code) — and an index on the coupon pointer,
 * which is how a coupon finds its own code. See docs/discounts.md#codes.
 *
 * A shop holding two rows whose codes differ only in case or spaces fails
 * this ALTER loudly (duplicate entry) — the same precedent as
 * {@see MakeCustomerUserUnique}. Merge or rename those rows by hand first.
 *
 * The same ALTER drops revision 17's plain `idx_code`: the unique index
 * serves every lookup by code by itself. down() puts it back under its own
 * name, so revision 17's down() still finds it, and drops the unique and the
 * pointer index; the codes stay uppercase.
 */
class MakeDiscountCodeUnique extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->run(
            'UPDATE `ecommerce_discount_code` SET `code` = UPPER(TRIM(`code`))'
        );

        $this->queryBuilder
            ->table('ecommerce_discount_code')
            ->alter(function (TableBuilder $table) {
                $table->dropIndex('code');
                $table->addUnique('code');
                $table->addIndex(['coupon_class', 'coupon_id']);
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder
            ->table('ecommerce_discount_code')
            ->alter(function (TableBuilder $table) {
                $table->dropIndex(['coupon_class', 'coupon_id']);
                $table->dropUnique('code');

                // Under the name revision 17's down() expects to find.
                $table->addIndex('code');
            });

        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Codes in ecommerce_discount_code uppercased, unique ' .
            'constraint added on code, redundant idx_code dropped, index ' .
            'on the coupon pointer';
    }

    public function describeDown(): string
    {
        return 'Unique constraint dropped from ecommerce_discount_code.code, ' .
            'idx_code restored, index on the coupon pointer dropped';
    }
}
