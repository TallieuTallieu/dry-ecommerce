<?php

namespace Tnt\Ecommerce\Account;

use Tnt\Account\Contracts\AuthenticationInterface;
use Tnt\Ecommerce\Contracts\UserResolverInterface;

/**
 * The signed-in user, read from `dry-accounts` — the whole of the pairing
 * between the two packages. The only class in src/ naming `Tnt\Account`, and
 * only in a lazily-resolved type hint, which is what keeps dry-accounts out
 * of `require`. Checks signed-in, deliberately not activated. See
 * docs/customer.md.
 */
final class AccountsUserResolver implements UserResolverInterface
{
    private AuthenticationInterface $authentication;

    /**
     * @param AuthenticationInterface $authentication
     */
    public function __construct(AuthenticationInterface $authentication)
    {
        $this->authentication = $authentication;
    }

    /**
     * The signed-in user's id, or null when nobody is signed in.
     *
     * @return int|null
     */
    public function getCurrentUserId(): ?int
    {
        return $this->authentication->getUser()?->getIdentifier();
    }
}
