<?php

namespace Tnt\Ecommerce\Account;

use Tnt\Account\Contracts\AuthenticationInterface;
use Tnt\Ecommerce\Contracts\UserResolverInterface;

/**
 * The signed-in user, read from `dry-accounts`.
 *
 * The whole of the pairing between the two packages, and the reason a shop
 * running both needs no glue of its own: bind this and a checkout by a signed-in
 * visitor links its customer row to that visitor's account.
 *
 * ```php
 * // config/ecommerce.php
 * 'user_resolver' => \Tnt\Ecommerce\Account\AccountsUserResolver::class,
 * ```
 *
 * # Naming `dry-accounts` here is safe; naming it in `require` would not be
 *
 * This is the one class in `src/` that mentions `Tnt\Account`, and it does so
 * only in a constructor type hint. PHP resolves that lazily, so a shop without
 * `dry-accounts` installed never loads this file — nothing references it unless
 * the config names it, and a shop with no accounts has no reason to. The
 * package therefore still installs and runs with `dry-accounts` absent, which is
 * why it is in `require-dev` rather than `require`: present for this file's
 * static analysis and for the tests, required of nobody.
 *
 * `AuthenticationInterface` rather than the `Auth` facade, because a facade
 * resolves through a booted application and cannot be handed a signed-in user
 * in a test. The interface can, and {@see \Tests\Feature\AccountsPairingTest}
 * does exactly that.
 *
 * # What it deliberately does not check
 *
 * Only whether somebody is signed in — not whether their account is activated,
 * which `dry-accounts` tracks separately and exposes as
 * `isAuthenticatedAndActivated()`. Whether an unactivated account may buy
 * something is trade policy, and a shop that wants to refuse the sale has a
 * better place to refuse it than halfway through writing an order. Refusing it
 * here would also do the wrong thing: it would not block the checkout, it would
 * silently record it as a guest one and lose the link. Reporting the account
 * that is signed in is this class's whole job.
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
