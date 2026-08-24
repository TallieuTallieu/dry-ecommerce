<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Account\Contracts\AuthenticationInterface;
use Tnt\Account\Contracts\User\UserInterface;

/**
 * A signed-in visitor, standing in for `dry-accounts`' real authentication.
 *
 * Only `getUser()` carries anything; the rest of the interface is here because
 * the interface has it. That imbalance is the point of the test that uses this
 * — {@see \Tnt\Ecommerce\Account\AccountsUserResolver} asks `dry-accounts`
 * exactly one of these nine questions, and a double that has to answer the
 * other eight to be built is the cheapest possible proof of it.
 *
 * The real `Tnt\Account\Authentication` reads the session and then loads the
 * user from the database on every call, which is why this substitutes for the
 * interface rather than the class.
 */
final class FakeAuthentication implements AuthenticationInterface
{
    private ?UserInterface $user;

    /**
     * @param UserInterface|null $user The signed-in user, or null for a guest.
     */
    public function __construct(?UserInterface $user = null)
    {
        $this->user = $user;
    }

    /**
     * @return UserInterface|null
     */
    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    /**
     * @param string $authIdentifier
     * @param string $password
     * @param array<string, mixed> $data
     * @return UserInterface|null
     */
    public function register(
        string $authIdentifier,
        string $password,
        array $data = []
    ): ?UserInterface {
        return null;
    }

    /**
     * @param string $authIdentifier
     * @param string $password
     * @return bool
     */
    public function authenticate(string $authIdentifier, string $password): bool
    {
        return false;
    }

    /**
     * @param string $authIdentifier
     * @param string $password
     * @return bool
     */
    public function authenticateActivated(
        string $authIdentifier,
        string $password
    ): bool {
        return false;
    }

    /**
     * @return void
     */
    public function logout()
    {
        $this->user = null;
    }

    /**
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    /**
     * @return bool
     */
    public function isAuthenticatedAndActivated(): bool
    {
        return $this->user !== null;
    }

    /**
     * @param string $authIdentifier
     * @return UserInterface|null
     */
    public function getActivatedUser(string $authIdentifier): ?UserInterface
    {
        return null;
    }
}
