<?php

namespace Tnt\Ecommerce\Repository;

use Tnt\Dbi\Criteria\Equals;
use Tnt\Ecommerce\Model\DiscountCode;

/**
 * Reads `ecommerce_discount_code`.
 *
 * The one thing a shop does with discount codes is turn the string a visitor
 * typed into a row, which is {@see byCode()}. Whether the coupon behind that
 * row is still redeemable is the coupon's business, not this query's.
 *
 * @extends Repository<DiscountCode>
 */
class DiscountCodeRepository extends Repository
{
    protected string $model = DiscountCode::class;

    /**
     * Filter by the code as typed, trimmed.
     *
     * @param string $code
     * @return static
     */
    public function byCode(string $code): static
    {
        $this->addCriteria(new Equals('code', trim($code)));

        return $this;
    }
}
