<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Cart;

use Oak\Contracts\Cookie\CookieInterface;
use Tnt\Ecommerce\Model\Cart as CartModel;
use Tnt\Ecommerce\Repository\CartRepository;

/**
 * A cart that outlives the session: a dedicated cookie holds the cart row's
 * `token` — never the row id, which is guessable — and the row is found by
 * it. A token pointing at a missing or soft-deleted cart reads as no cart at
 * all. The expiry slides: finding the cart writes the cookie again, so the
 * lifetime counts from the visitor's last visit, not their first item. Bound
 * instead of {@see SessionCartStorage} when `ecommerce.cart_lifetime` is set;
 * see docs/cart.md.
 */
class CookieCartStorage extends DatabaseCartStorage
{
    /**
     * The cookie holding the visitor's cart token.
     */
    public const COOKIE_NAME = 'ecommerce_cart';

    /**
     * What a token looks like: bin2hex(random_bytes(16)), minted at row
     * creation by {@see DatabaseCartStorage::cart()}. Anything else in the
     * cookie is not a token and is never sent to a query.
     */
    private const TOKEN_PATTERN = '/^[0-9a-f]{32}$/';

    private CookieInterface $cookie;

    private int $lifetimeDays;

    /**
     * @param CookieInterface $cookie
     * @param int $lifetimeDays How long the cookie names the cart, in days —
     *                          `ecommerce.cart_lifetime`, and also what the
     *                          draft reaper counts against.
     */
    public function __construct(CookieInterface $cookie, int $lifetimeDays)
    {
        $this->cookie = $cookie;
        $this->lifetimeDays = $lifetimeDays;
    }

    /**
     * @return CartModel|null
     */
    protected function findCart(): ?CartModel
    {
        $token = $this->token();

        if ($token === null) {
            return null;
        }

        $cart = $this->loadByToken($token);

        // A living cart found is a visit, and a visit re-stamps the cookie a
        // full lifetime from now — otherwise a basket in daily use would
        // still expire `cart_lifetime` days after its FIRST item, while the
        // draft reaper reads the same knob as days-since-last-touch.
        if ($cart !== null && $cart->deleted === null) {
            $this->remember($cart);
        }

        return $cart;
    }

    /**
     * The token the cookie holds, if it holds one worth querying on.
     *
     * @return string|null
     */
    private function token(): ?string
    {
        $raw = $this->cookie->get(self::COOKIE_NAME);

        if (!is_string($raw) || preg_match(self::TOKEN_PATTERN, $raw) !== 1) {
            return null;
        }

        return $raw;
    }

    /**
     * The living cart a token names, or null. A test seam, same shape as
     * {@see Cart::newOrder()} — the query is the only line here that needs a
     * database.
     *
     * @param string $token
     * @return CartModel|null
     */
    protected function loadByToken(string $token): ?CartModel
    {
        return CartRepository::create()
            ->byToken($token)
            ->notDeleted()
            ->firstOrNull();
    }

    /**
     * @param CartModel $cart
     * @return void
     */
    protected function remember(CartModel $cart): void
    {
        $this->cookie->set(
            self::COOKIE_NAME,
            (string) $cart->token,
            time() + $this->lifetimeDays * 86400
        );
    }

    /**
     * @return void
     */
    protected function forget(): void
    {
        // An empty value with an expiry in the past: the browser drops the
        // cookie, and until it does, '' is not a token and reads as no cart.
        $this->cookie->set(self::COOKIE_NAME, '', time() - 86400);
    }
}
