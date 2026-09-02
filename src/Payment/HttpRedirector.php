<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Payment;

use dry\http\Response;
use Tnt\Ecommerce\Contracts\RedirectorInterface;

/**
 * The shipped redirector: dry's own redirect, which sends the header and
 * exits. Bound to {@see RedirectorInterface} by the service provider; a test
 * substitutes one that records instead. See docs/payment.md.
 */
final class HttpRedirector implements RedirectorInterface
{
    /**
     * 302, not dry's default 301: a checkout URL is one payment attempt's,
     * and a cached permanent redirect would replay it on the next attempt.
     *
     * @param string $url
     * @return void
     */
    public function redirect(string $url): void
    {
        Response::redirect($url, 302);
    }
}
