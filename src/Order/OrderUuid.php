<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Order;

/**
 * An order's `uuid`: the key a URL names it by, so the URL cannot be guessed
 * from the reference — which a shop may well make sequential. Version 4, all
 * random: a v7 would print when the order was made. See
 * docs/orders.md#the-uuid.
 */
final class OrderUuid
{
    /**
     * Lower-case and hyphenated, `xxxxxxxx-xxxx-4xxx-[89ab]xxx-xxxxxxxxxxxx`,
     * as the revision's backfill writes them too.
     *
     * @return string
     *
     * @throws \Random\RandomException If the system has no source of secure
     *                                 randomness — deliberately not caught.
     */
    public static function generate(): string
    {
        $bytes = random_bytes(16);

        // RFC 9562 §5.4: the version in the high nibble of byte 6, the
        // variant in the two high bits of byte 8.
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
