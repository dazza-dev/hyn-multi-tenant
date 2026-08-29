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

namespace Hyn\Tenancy\Tests\Middleware;

use Exception;
use Hyn\Tenancy\Contracts\CurrentHostname;
use Hyn\Tenancy\Contracts\Hostname;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Middleware\HostnameActions;
use Hyn\Tenancy\Tests\Test;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HostnameActionsTest extends Test
{
    public const RESPONSE = 'ok';

    /**
     * @test
     */
    public function under_maintenance()
    {
        $this->hostname->under_maintenance_since = Carbon::now();
        $this->hostname->save();

        try {
            $this->middleware($this->hostname);

            $this->fail('Middleware didn\'t fire maintenance exception');
        } catch (HttpException $e) {
            $this->assertEquals(503, $e->getStatusCode());
        }

        $this->hostname->under_maintenance_since = null;
        $this->hostname->save();

        // Rebind the updated model.
        $this->app->bind(CurrentHostname::class, function () {
            return $this->hostname;
        });

        $this->assertEquals(static::RESPONSE, $this->middleware($this->hostname));
    }

    /**
     * @test
     */
    public function middleware_allows_empty_hostname()
    {
        $middleware = new HostnameActions(app()->make(Redirector::class));

        $this->assertNotNull($middleware);
    }

    /**
     * @test
     */
    public function auto_identification_false()
    {
        config(['tenancy.hostname.auto-identification' => false]);
        config(['tenancy.hostname.abort-without-identified-hostname' => true]);

        try {
            $middleware = new HostnameActions(app()->make(Redirector::class));

            $request = new Request();

            $middleware->handle($request, function () {
                return static::RESPONSE;
            });
        } catch (Exception $e) {
            $this->assertInstanceOf(NotFoundHttpException::class, $e);
        }
    }

    /**
     * @test
     */
    public function redirects_when_the_hostname_points_elsewhere()
    {
        $this->hostname->redirect_to = 'https://elsewhere.testing';
        $this->hostname->save();

        $response = $this->middleware($this->hostname);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://elsewhere.testing', $response->getTargetUrl());
    }

    /**
     * @test
     */
    public function forces_https_when_the_hostname_demands_it()
    {
        $this->hostname->force_https = true;
        $this->hostname->save();

        $response = $this->middleware($this->hostname);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringStartsWith('https://', $response->getTargetUrl());
    }

    /**
     * Maintenance takes precedence: a hostname both under maintenance and
     * redirecting must not send visitors on to a site that is down.
     *
     * @test
     */
    public function maintenance_wins_over_a_redirect()
    {
        $this->hostname->under_maintenance_since = Carbon::now();
        $this->hostname->redirect_to = 'https://elsewhere.testing';
        $this->hostname->save();

        $this->expectException(HttpException::class);

        $this->middleware($this->hostname);
    }

    protected function middleware(?Hostname $set = null)
    {
        app(Environment::class)->hostname($set);

        $identified = $this->app->make(CurrentHostname::class);

        if ($set) {
            $this->assertNotNull($identified);
        } else {
            $this->assertNull($identified);
        }

        $request = new Request();
        $middleware = new HostnameActions(app()->make(Redirector::class));

        return $middleware->handle($request, function () {
            return static::RESPONSE;
        });
    }

    protected function duringSetUp(Application $app)
    {
        $this->setUpHostnames();
    }
}
