<?php

/*
 * This file is part of the hyn/multi-tenant package.
 *
 * (c) Daniël Klabbers <daniel@klabbers.email>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see https://tenancy.dev
 * @see https://github.com/hyn/multi-tenant
 */

namespace Hyn\Tenancy\Middleware;

use Closure;
use Hyn\Tenancy\Environment;
use Illuminate\Http\Request;

class EagerIdentification
{
    public function handle(Request $request, Closure $next)
    {
        if (config('tenancy.hostname.auto-identification') &&
            config('tenancy.hostname.early-identification')) {
            // Identification reads the request, and asking again per request
            // is what keeps a long lived process from serving every one of
            // them the tenant it identified for the first.
            app(Environment::class)->identifyHostname();
        }

        return $next($request);
    }
}
