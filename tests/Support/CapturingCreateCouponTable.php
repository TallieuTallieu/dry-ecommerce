<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\CreateCouponTable;

/**
 * The real revision, stopped just short of the database.
 */
final class CapturingCreateCouponTable extends CreateCouponTable
{
    use CapturesRevisionStatements;
}
