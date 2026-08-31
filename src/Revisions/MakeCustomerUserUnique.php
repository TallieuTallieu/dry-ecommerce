<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Revisions;

use Oak\Contracts\Migration\RevisionInterface;
use Tnt\Dbi\TableBuilder;

/**
 * One customer row per account, enforced by the schema: UNIQUE on
 * `ecommerce_customer.user`. The column stays nullable, and MySQL uniques
 * ignore NULLs — every guest-era row is untouched; only real accounts are
 * held to one row. This is the lock {@see \Tnt\Ecommerce\Model\Customer::forUser()}
 * leans on when two requests race the same insert. See docs/customer.md.
 *
 * The same ALTER drops sc-11263's `idx_user_created`: under the unique at
 * most one row matches any `user`, so its sort suffix orders nothing, and
 * the unique index serves the byUser() equality by itself. Safe to drop —
 * `user` carries no foreign key (by design; the target table belongs to
 * dry-accounts), so no constraint is leaning on either index. Verified
 * against the compose MySQL 8.0.34, both directions.
 *
 * A shop already holding two rows for one account fails this ALTER loudly
 * (duplicate entry) — merge those rows by hand before migrating.
 */
class MakeCustomerUserUnique extends DatabaseRevision implements
    RevisionInterface
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->queryBuilder
            ->table('ecommerce_customer')
            ->alter(function (TableBuilder $table) {
                $table->dropIndex(['user', 'created']);
                $table->addUnique('user');
            });

        $this->execute();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->queryBuilder
            ->table('ecommerce_customer')
            ->alter(function (TableBuilder $table) {
                $table->dropUnique('user');

                // Under the name revision 17's down() expects to find.
                $table->addIndex(['user', 'created']);
            });

        $this->execute();
    }

    public function describeUp(): string
    {
        return 'Unique constraint added on ecommerce_customer.user, ' .
            'redundant idx_user_created dropped';
    }

    public function describeDown(): string
    {
        return 'Unique constraint dropped from ecommerce_customer.user, ' .
            'idx_user_created restored';
    }
}
