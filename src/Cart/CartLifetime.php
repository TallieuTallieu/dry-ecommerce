<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Cart;

use Oak\Contracts\Config\RepositoryInterface;

/**
 * Reads `ecommerce.cart_lifetime` — how many days a cart (and the draft it
 * carries) stays alive. One reader, because two things must agree on it: the
 * provider binding {@see CookieCartStorage}, and the draft reaper. See
 * docs/installation.md.
 */
final class CartLifetime
{
    private function __construct() {}

    /**
     * The configured lifetime in days, or null when the shop has not set one
     * — unset, zero, negative or not an int all read as "no lifetime", the
     * reading that keeps today's session-backed behaviour.
     *
     * @param RepositoryInterface $config
     * @return int|null
     */
    public static function days(RepositoryInterface $config): ?int
    {
        $configured = $config->get('ecommerce.cart_lifetime');

        if (!is_int($configured) || $configured <= 0) {
            return null;
        }

        return $configured;
    }
}
