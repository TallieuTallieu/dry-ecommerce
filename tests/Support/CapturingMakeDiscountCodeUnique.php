<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\MakeDiscountCodeUnique;

/**
 * The real revision, stopped just short of the database.
 */
final class CapturingMakeDiscountCodeUnique extends MakeDiscountCodeUnique
{
    use CapturesRevisionStatements;
}
