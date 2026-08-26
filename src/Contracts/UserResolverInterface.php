<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Who, if anyone, is signed in when a checkout happens — the one question
 * this package asks about accounts. An id and not a user object, so the
 * contract names no class from a package that is not a dependency. See
 * docs/customer.md.
 *
 * @see \Tnt\Ecommerce\Account\GuestUserResolver
 * @see \Tnt\Ecommerce\Account\AccountsUserResolver
 */
interface UserResolverInterface
{
    /**
     * The id of the signed-in user, or null when nobody is signed in — null
     * is a guest checkout, not a failure.
     *
     * @return int|null
     */
    public function getCurrentUserId(): ?int;
}
