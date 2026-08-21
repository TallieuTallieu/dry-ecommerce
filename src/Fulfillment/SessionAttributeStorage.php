<?php

namespace Tnt\Ecommerce\Fulfillment;

use Oak\Session\Session;
use Tnt\Ecommerce\Contracts\AttributeStorageInterface;

/**
 * Fulfillment attributes kept in the session, which is where they have always
 * been kept — under a single `fulfillmentAttributes` key shared by every
 * fulfillment method, so the behaviour is unchanged.
 *
 * What did change is that the session arrives by injection instead of through
 * its facade, so a fulfillment method can now be exercised without one.
 */
class SessionAttributeStorage implements AttributeStorageInterface
{
    /**
     * The session key the whole bag lives under.
     */
    public const SESSION_KEY = 'fulfillmentAttributes';

    private Session $session;

    /**
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        $attributes = $this->session->get(self::SESSION_KEY);

        return is_array($attributes) ? $attributes : [];
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function get(string $name)
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function set(string $name, $value): void
    {
        $attributes = $this->all();
        $attributes[$name] = $value;

        $this->session->set(self::SESSION_KEY, $attributes);
        $this->session->save();
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }
}
