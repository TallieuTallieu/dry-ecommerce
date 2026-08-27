<?php

declare(strict_types=1);

namespace Tests\Support;

use Oak\Contracts\Cookie\CookieInterface;

/**
 * Oak's cookie service without the browser: values in an array, expiries
 * remembered for asserting on. Oak's real service JSON-encodes on set and
 * decodes on get, which is the identity for the strings a cart token is —
 * so storing raw is the same round trip minus the headers a test cannot send.
 */
final class FakeCookie implements CookieInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    /**
     * The expiry each set() asked for, by cookie name.
     *
     * @var array<string, int>
     */
    public array $expiries = [];

    /**
     * @param string $name
     * @param mixed $value
     * @param int $expire
     * @return mixed|void
     */
    public function set(string $name, $value, int $expire = 0)
    {
        $this->values[$name] = $value;
        $this->expiries[$name] = $expire;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function get(string $name)
    {
        return $this->values[$name] ?? null;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->values[$name]);
    }
}
