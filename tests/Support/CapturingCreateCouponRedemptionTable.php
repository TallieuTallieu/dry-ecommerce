<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\CreateCouponRedemptionTable;

/**
 * The real revision, stopped just short of the database.
 */
final class CapturingCreateCouponRedemptionTable extends
    CreateCouponRedemptionTable
{
    use CapturesRevisionStatements;
}
