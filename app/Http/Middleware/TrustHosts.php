<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * A container or developer machine is commonly reached by either
     * `localhost` or `127.0.0.1`. Trusting only the host inside APP_URL made
     * the other loopback name fail with a bare 400. The aliases are added
     * only when the application URL is already loopback, so a deployment on a
     * real hostname keeps host-header validation unchanged.
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        $hosts = [
            $this->allSubdomainsOfApplicationUrl(),
        ];

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (in_array($appHost, ['localhost', '127.0.0.1', '::1'], true)) {
            $hosts[] = 'localhost';
            $hosts[] = '127.0.0.1';
            $hosts[] = '\[::1\]';
        }

        return $hosts;
    }
}
