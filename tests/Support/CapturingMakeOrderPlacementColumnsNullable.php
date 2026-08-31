<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\MakeOrderPlacementColumnsNullable;

/**
 * The real placement-columns revision, stopped just short of the database.
 */
final class CapturingMakeOrderPlacementColumnsNullable extends
    MakeOrderPlacementColumnsNullable
{
    use CapturesRevisionSql;
}
