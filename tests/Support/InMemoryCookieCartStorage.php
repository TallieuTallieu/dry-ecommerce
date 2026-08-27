<?php

declare(strict_types=1);

namespace Tests\Support;

use Oak\Contracts\Cookie\CookieInterface;
use Tnt\Ecommerce\Cart\CookieCartStorage;
use Tnt\Ecommerce\Model\Cart as CartModel;

/**
 * The real {@see CookieCartStorage}, stopped just short of the database: the
 * token lookup reads a {@see CartTable} and the row it creates is an
 * {@see InMemoryCart}. Everything else — the cookie read, the token check,
 * the minting, the soft-delete refusal — is the production code.
 */
final class InMemoryCookieCartStorage extends CookieCartStorage
{
    private CartTable $table;

    public function __construct(
        CookieInterface $cookie,
        int $lifetimeDays,
        CartTable $table
    ) {
        parent::__construct($cookie, $lifetimeDays);

        $this->table = $table;
    }

    /**
     * @param string $token
     * @return CartModel|null
     */
    protected function loadByToken(string $token): ?CartModel
    {
        return $this->table->byToken($token);
    }

    /**
     * @return CartModel
     */
    protected function newCartRow(): CartModel
    {
        return new InMemoryCart($this->table);
    }
}
