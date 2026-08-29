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

use Hyn\Tenancy\Contracts\CurrentHostname;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Models\Website;
use Hyn\Tenancy\Tests\Test;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
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

        // A real request gets a new application. Within one, the hostname is
        // identified once and the binding is what holds it.
        $this->app->forgetInstance(CurrentHostname::class);
        resolve(Environment::class)->forgetTenant();

        $this->get('http://second.testing/active-tenant')
            ->assertSee($second->uuid);
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
