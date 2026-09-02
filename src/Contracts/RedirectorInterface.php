<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Contracts;

/**
 * How a gateway sends the visitor away — to the provider's checkout page,
 * or straight to the shop's return page. A seam rather than a direct call to
 * dry's Response so a gateway's pay() can run in a test without exiting the
 * process. See docs/payment.md.
 */
interface RedirectorInterface
{
    /**
     * Send the visitor to a URL. The shipped implementation never returns —
     * dry answers a redirect by exiting — so treat everything after this
     * call as unreachable.
     *
     * @param string $url
     * @return void
     */
    public function redirect(string $url): void;
}
