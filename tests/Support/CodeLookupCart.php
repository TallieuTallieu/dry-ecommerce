<?php

declare(strict_types=1);

namespace Tests\Support;

use LogicException;
use Tnt\Ecommerce\Cart\Cart;
use Tnt\Ecommerce\Cart\InMemoryCartStorage;
use Tnt\Ecommerce\Model\DiscountCode;

/**
 * The real cart, looking typed codes up in memory instead of
 * `ecommerce_discount_code` — one overridden method, as
 * {@see InMemoryOrderCart} overrides one. Normalising, refusing and storing
 * the code are production code.
 */
final class CodeLookupCart extends Cart
{
    /**
     * The code rows there are, keyed by their stored code.
     *
     * @var array<string, DiscountCode>
     */
    public array $codes = [];

    /**
     * How often a typed code was looked up.
     */
    public int $lookups = 0;

    private ?InMemoryCartStorage $memory = null;

    /**
     * Store a row the way {@see DiscountCode::save()} would: normalised.
     *
     * @param DiscountCode $code
     * @return $this
     */
    public function knowing(DiscountCode $code): self
    {
        $code->code = DiscountCode::normalise((string) $code->code);
        $this->codes[$code->code] = $code;

        return $this;
    }

    /**
     * The storage this cart was built with, so a test reads the code it
     * holds — not {@see getDiscount()}, which hides one that stopped
     * applying.
     *
     * @param InMemoryCartStorage $storage
     * @return $this
     */
    public function storedIn(InMemoryCartStorage $storage): self
    {
        $this->memory = $storage;

        return $this;
    }

    /**
     * The code the storage holds, whether or not it still applies.
     *
     * @return DiscountCode|null
     */
    public function stored(): ?DiscountCode
    {
        if ($this->memory === null) {
            throw new LogicException(
                'This cart was not told its storage — see storedIn().'
            );
        }

        return $this->memory->getDiscount();
    }

    /**
     * An exact-key lookup: the cart normalises before it asks, as
     * {@see \Tnt\Ecommerce\Repository\DiscountCodeRepository::byCode()}
     * matches.
     *
     * @param string $code
     * @return DiscountCode|null
     */
    protected function findCode(string $code): ?DiscountCode
    {
        $this->lookups++;

        return $this->codes[$code] ?? null;
    }
}
