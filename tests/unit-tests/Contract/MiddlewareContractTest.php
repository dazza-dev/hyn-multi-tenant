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

namespace Hyn\Tenancy\Tests\Contract;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Events\Websites\Switched;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Models\Website;
use Hyn\Tenancy\Tests\Test;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use ReflectionProperty;

/**
 * The package installs itself into the request by prepending middleware to the
 * global stack. Nothing else in the suite reaches a booted HTTP kernel, so a
 * broken installation shows up only here.
 */
class MiddlewareContractTest extends Test
{
    protected function duringSetUp(Application $app)
    {
        $this->setUpHostnames(true);
        $this->setUpWebsites(true, true);
    }

    protected function defineRoutes($router)
    {
        $router->get('active-tenant', function () {
            return (string) optional(app(Environment::class)->tenant())->uuid;
        });
    }

    /**
     * @test
     */
    public function the_configured_middleware_lead_the_global_stack()
    {
        $configured = config('tenancy.middleware');

        $this->assertEquals(
            $configured,
            array_slice($this->globalMiddleware(), 0, count($configured)),
            'Tenancy middleware do not lead the global stack, in the configured order.'
        );
    }

    /**
     * @test
     */
    public function a_request_is_served_the_tenant_of_the_hostname_it_asks_for()
    {
        $second = new Website();
        $this->websites->create($second);

        $hostname = $this->hostnameFor('second.testing');

        $this->hostnames->attach($hostname, $second);

        $this->get('http://' . $this->hostname->fqdn . '/active-tenant')
            ->assertSee($this->website->uuid);

        $this->get('http://second.testing/active-tenant')
            ->assertSee($second->uuid);
    }

    /**
     * A request settles on one tenant, once.
     *
     * @test
     */
    public function a_request_switches_tenant_once()
    {
        $switched = 0;

        Event::listen(Switched::class, function () use (&$switched) {
            $switched++;
        });

        $this->get('http://' . $this->hostname->fqdn . '/active-tenant');

        $this->assertEquals(1, $switched);
    }

    /**
     * Without a default hostname configured, an unknown one has no tenant to
     * fall back to, and must be served none.
     *
     * @test
     */
    public function a_request_to_an_unknown_hostname_is_served_no_tenant()
    {
        config(['tenancy.hostname.default' => null]);

        $this->get('http://nobody.testing/active-tenant')
            ->assertDontSee($this->website->uuid);
    }

    protected function hostnameFor(string $fqdn): Hostname
    {
        $hostname = Hostname::unguarded(function () use ($fqdn) {
            return Hostname::firstOrNew(['fqdn' => $fqdn]);
        });

        if (! $hostname->exists) {
            $this->hostnames->create($hostname);
        }

        return $hostname;
    }

    protected function globalMiddleware(): array
    {
        $kernel = $this->app->make(Kernel::class);

        $property = new ReflectionProperty($kernel, 'middleware');
        $property->setAccessible(true);

        return $property->getValue($kernel);
    }
}
