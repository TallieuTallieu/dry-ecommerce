<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\DropAddressNameColumns;

/**
 * The real name-column drop, stopped just short of the database.
 *
 * Its own `execute()` rather than {@see CapturesRevisionSql} because it runs
 * a statement per table — one ALTER for the address book, one for the order —
 * and the trait's single `$sql` would keep only the last of them.
 */
final class CapturingDropAddressNameColumns extends DropAddressNameColumns
{
    /**
     * Every statement the revision built, in order, or [] before up()/down().
     *
     * @var array<int, string>
     */
    public array $statements = [];

    /**
     * @return void
     */
    protected function execute(): void
    {
        $this->queryBuilder->build();

        $this->statements[] = $this->queryBuilder->getQuery();
    }
}
