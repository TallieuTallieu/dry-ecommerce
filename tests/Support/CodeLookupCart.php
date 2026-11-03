<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Cart\Cart;
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

    protected function findCode(string $typed): ?DiscountCode
    {
        $this->lookups++;

        // As DiscountCodeRepository::byCode() matches.
        return $this->codes[DiscountCode::normalise($typed)] ?? null;
    }
}
