<?php

namespace Tnt\Ecommerce\Fulfillment;

use Oak\Session\Facade\Session;

trait HasFulfillmentAttributes
{
    /**
     * @var array $attributes
     */
    private $attributes = [];

    /**
     * @param string $name
     */
    private function restoreSessionAttributes()
    {
        if (! Session::get('fulfillmentAttributes')) {
            $this->saveSessionAttributes();
        }

        $this->attributes = Session::get('fulfillmentAttributes');
    }

    /**
     *
     */
    private function saveSessionAttributes()
    {
        Session::set('fulfillmentAttributes', $this->attributes);
        Session::save();
    }

    /**
     * @param string $name
     * @return null
     * @throws MissingAttribute
     */
    public function getAttribute(string $name)
    {
        $this->restoreSessionAttributes();
        $reqAttrs = $this->requireAttributes();

        if (! $this->hasAttribute($name)) {
            if (in_array($name, $reqAttrs)) {
                throw new MissingAttribute($name);
            }
            return null;
        }

        return $this->attributes[$name];
    }

    /**
     * @param string $name
     * @param $value
     * @return mixed
     */
    public function setAttribute(string $name, $value)
    {
        $this->restoreSessionAttributes();
        $this->attributes[$name] = $value;
        $this->saveSessionAttributes();
    }

    /**
     * @return array
     */
    public function requireAttributes(): array
    {
        return [];
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasAttribute(string $name): bool
    {
        $this->restoreSessionAttributes();
        return (isset($this->attributes[$name]));
    }

    /**
     * @return bool
     */
    public function validateAttributes(): bool
    {
        $this->restoreSessionAttributes();

        foreach ($this->requireAttributes() as $reqAttr) {
            if (! $this->hasAttribute($reqAttr)) {
                return false;
            }
        }

        return true;
    }
}