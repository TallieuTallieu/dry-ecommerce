<?php

namespace Tnt\Ecommerce\Account;

use Tnt\Ecommerce\Contracts\UserResolverInterface;

/**
 * Nobody is ever signed in.
 *
 * The default binding for {@see UserResolverInterface}, and the right answer
 * for a shop with no accounts: there are no users, so no checkout can be by
 * one. Every customer row it produces is unlinked, which is exactly what a
 * guest checkout is.
 *
 * It is a default in the sense {@see \Tnt\Ecommerce\Payment\NullPayment} is —
 * something a shop that has not configured the seam can genuinely run on —
 * rather than in the sense the retired `NullStockWorker` and `NullTaxRate`
 * were, which existed only so a buyable could pretend to answer a question it
 * should not have been asked. There is a real question here and this is a real
 * answer to it.
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
