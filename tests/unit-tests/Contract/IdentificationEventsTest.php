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
use Hyn\Tenancy\Events;
use Hyn\Tenancy\Tests\TestCase;
use Illuminate\Contracts\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;

/**
 * The order of the events fired while a tenant is identified, and what they
 * carry.
 *
 * Applications hook into these to shape the tenant configuration —
 * ConfigurationLoading is the documented place to do it — so both the order
 * and the payload are contract, not implementation detail.
 */
class IdentificationEventsTest extends TestCase
{
    /** @var Environment */
    protected $environment;

    /** @var array */
    protected $recorded = [];

    #[Test]
    public function the_configuration_is_built_before_the_connection_is_set()
    {
        $this->record();

        $this->environment->tenant($this->website);

        $this->assertSame([
            'Database\ConfigurationLoading',
            'Database\ConfigurationLoaded',
            'Database\ConnectionSet',
        ], $this->recordedFor('Database'));
    }

    /**
     * ConfigurationLoading hands the array over by reference, which is how an
     * application supplies its own credentials — the whole point of the bypass
     * division mode.
     */
    #[Test]
    public function a_listener_can_shape_the_configuration_before_the_connection_opens()
    {
        $this->app->make('events')->listen(
            Events\Database\ConfigurationLoading::class,
            function (Events\Database\ConfigurationLoading $event) {
                $event->configuration['shaped_by_a_listener'] = 'yes';
            }
        );

        $this->environment->tenant($this->website);

        $this->assertSame(
            'yes',
            $this->connection->configuration()['shaped_by_a_listener'] ?? null,
            'What a ConfigurationLoading listener writes must reach the connection.'
        );
    }

    #[Test]
    public function identifying_a_hostname_connects_its_tenant()
    {
        $this->record();

        $this->environment->identifyHostname();
        $this->app->make(CurrentHostname::class);

        $this->assertContains('Websites\Identified', $this->recorded);
        $this->assertSame([
            'Database\ConfigurationLoading',
            'Database\ConfigurationLoaded',
            'Database\ConnectionSet',
        ], array_slice($this->recordedFor('Database'), 0, 3));

        $this->assertSame($this->website->uuid, $this->connection->configuration()['uuid'] ?? null);
        $this->assertTrue($this->website->is($this->environment->tenant()));
    }

    #[Test]
    public function the_loaded_configuration_carries_the_tenant_and_its_uuid()
    {
        $loaded = null;

        $this->app->make('events')->listen(
            Events\Database\ConfigurationLoaded::class,
            function (Events\Database\ConfigurationLoaded $event) use (&$loaded) {
                $loaded = $event;
            }
        );

        $this->environment->tenant($this->website);

        $this->assertNotNull($loaded, 'No ConfigurationLoaded was fired for the tenant.');
        $this->assertTrue($this->website->is($loaded->website));
        $this->assertSame($this->website->uuid, $loaded->configuration['uuid']);
        $this->assertArrayHasKey(
            'driver',
            $loaded->configuration,
            'The configuration is handed over whole, driver included, for listeners to amend.'
        );
    }

    #[Test]
    public function releasing_the_tenant_announces_it()
    {
        $this->environment->tenant($this->website);

        $this->record();

        $this->environment->forgetTenant();

        $this->assertContains('Websites\Forgotten', $this->recorded);
        $this->assertSame([], $this->connection->configuration());
    }

    /**
     * The events recorded whose label starts with the given namespace.
     *
     * Only events fired by the same emitter can be ordered this way: a
     * listener registered here runs after the ones the package registered, so
     * an outer event lands in the list behind everything its own listeners
     * caused.
     */
    protected function recordedFor(string $namespace): array
    {
        return array_values(array_filter($this->recorded, function (string $label) use ($namespace) {
            return strpos($label, $namespace.'\\') === 0;
        }));
    }

    /**
     * Record the events of the identification cycle in the order they fire.
     */
    protected function record(): void
    {
        $this->recorded = [];

        $events = [
            Events\Websites\Identified::class => 'Websites\Identified',
            Events\Websites\Switched::class => 'Websites\Switched',
            Events\Websites\Forgotten::class => 'Websites\Forgotten',
            Events\Database\ConfigurationLoading::class => 'Database\ConfigurationLoading',
            Events\Database\ConfigurationLoaded::class => 'Database\ConfigurationLoaded',
            Events\Database\ConnectionSet::class => 'Database\ConnectionSet',
        ];

        $dispatcher = $this->app->make('events');

        foreach ($events as $class => $label) {
            $dispatcher->listen($class, function () use ($label) {
                $this->recorded[] = $label;
            });
        }
    }

    protected function duringSetUp(Application $app)
    {
        $this->environment = $app->make(Environment::class);

        $this->setUpHostnames(true);
        $this->setUpWebsites(true, true);
    }
}
