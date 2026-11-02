<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * What a lower-case, hyphenated version 4 UUID looks like — what
 * {@see \Tnt\Ecommerce\Order\OrderUuid} writes, on a new order and in the
 * uuid revision's backfill alike.
 */
final class UuidV4
{
    public const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
}
