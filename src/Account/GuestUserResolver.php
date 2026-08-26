<?php

namespace Tnt\Ecommerce\Account;

use Tnt\Ecommerce\Contracts\UserResolverInterface;

/**
 * Nobody is ever signed in — the default {@see UserResolverInterface}, and
 * the correct answer for a shop with no accounts.
 */
final class GuestUserResolver implements UserResolverInterface
{
    /**
     * Always null.
     *
     * @return int|null
     */
    public function getCurrentUserId(): ?int
    {
        return null;
    }
}
