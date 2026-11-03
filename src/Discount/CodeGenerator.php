<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Discount;

/**
 * A fresh discount code: eight characters of Crockford's base 32, the
 * alphabet {@see \Tnt\Ecommerce\Order\RandomOrderReference} uses — no `I`,
 * `L`, `O` or `U`, so a code survives being read out over a counter. 32^8 is
 * about a trillion codes; whether one is free is the caller's check.
 */
final class CodeGenerator
{
    public const LENGTH = 8;

    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private function __construct() {}

    /**
     * random_int(), never rand(): a code is a credential worth money.
     *
     * @return string
     *
     * @throws \Random\RandomException If the system has no source of secure
     *                                 randomness — deliberately not caught.
     */
    public static function generate(): string
    {
        $code = '';
        $last = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $last)];
        }

        return $code;
    }
}
