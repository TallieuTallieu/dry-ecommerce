<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\UserResolverInterface;

/**
 * A signed-in user, without an authentication package behind it.
 *
 * The whole seam is one question with an `int|null` answer, so standing in for
 * a signed-in visitor is a constructor argument. Built with null it is
 * indistinguishable from {@see \Tnt\Ecommerce\Account\GuestUserResolver}, which
 * is the point: the two checkout paths differ by this value and nothing else.
 */
final class FakeUserResolver implements UserResolverInterface
{
    private ?int $userId;

    /**
     * @param int|null $userId The id to answer with, or null for a guest.
     */
    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
    }

    /**
     * @return int|null
     */
    public function getCurrentUserId(): ?int
    {
        return $this->userId;
    }
}
