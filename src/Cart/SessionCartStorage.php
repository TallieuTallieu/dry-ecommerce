<?php

namespace Tnt\Ecommerce\Cart;

use Oak\Session\Session;
use Tnt\Ecommerce\Model\Cart as CartModel;
use Tnt\Ecommerce\Repository\CartRepository;

/**
 * The shipped default cart storage: the session says which cart row is the
 * visitor's, and the row plus its lines hold the rest ({@see
 * DatabaseCartStorage}). The cookie-backed alternative outlives the session —
 * see {@see CookieCartStorage} and docs/cart.md.
 */
class SessionCartStorage extends DatabaseCartStorage
{
    /**
     * The session key holding the current cart's id.
     */
    public const SESSION_KEY = 'cart';

    private Session $session;

    /**
     * Repositories are built per query, not injected: a dry-dbi repository
     * accumulates criteria, so one instance cannot serve two questions.
     *
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * @return CartModel|null
     */
    protected function findCart(): ?CartModel
    {
        $id = $this->session->get(self::SESSION_KEY);

        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return null;
        }

        return CartRepository::create()
            ->byId((int) $id)
            ->notDeleted()
            ->firstOrNull();
    }

    /**
     * @param CartModel $cart
     * @return void
     */
    protected function remember(CartModel $cart): void
    {
        $this->session->set(self::SESSION_KEY, $cart->id);
        $this->session->save();
    }

    /**
     * @return void
     */
    protected function forget(): void
    {
        $this->session->set(self::SESSION_KEY, null);
        $this->session->save();
    }
}
