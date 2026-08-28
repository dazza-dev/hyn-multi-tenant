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

use Hyn\Tenancy\Contracts\Database\PasswordGenerator;
use Hyn\Tenancy\Contracts\Website;
use Hyn\Tenancy\Database\Connection;
use Hyn\Tenancy\Exceptions\ConnectionException;
use Hyn\Tenancy\Tests\Test;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;

/**
 * The observable behaviour of Hyn\Tenancy\Database\Connection.
 *
 * Applications read the connection names out of it, and every division mode
 * shapes the tenant configuration differently. Both are relied on outside the
 * package, so both are pinned here rather than left to the modes that happen
 * to run in CI.
 */
class ConnectionContractTest extends Test
{
    /**
     * An empty name is not a connection called nothing. Leaving
     * TENANCY_TENANT_CONNECTION_NAME blank used to reach the database manager
     * as '', and setting it to null crashed on the return type.
     *
     * @test
     */
    public function an_empty_connection_name_falls_back_to_the_default()
    {
        foreach ([null, ''] as $empty) {
            $this->withConnectionNames($empty, $empty, function () {
                $this->assertSame(Connection::DEFAULT_TENANT_NAME, $this->connection->tenantName());
                $this->assertSame(Connection::DEFAULT_SYSTEM_NAME, $this->connection->systemName());
            });
        }
    }

    /**
     * @test
     */
    public function the_connection_names_follow_configuration()
    {
        $this->withConnectionNames('customer', 'central', function () {
            $this->assertSame('customer', $this->connection->tenantName());
            $this->assertSame('central', $this->connection->systemName());
        });
    }

    /**
     * @test
     */
    public function the_database_mode_names_the_database_after_the_uuid()
    {
        $configuration = $this->configurationFor(Connection::DIVISION_MODE_SEPARATE_DATABASE);

        $this->assertSame($this->website->uuid, $configuration['database']);
        $this->assertSame($this->website->uuid, $configuration['username']);
        $this->assertSame(
            app(PasswordGenerator::class)->generate($this->website),
            $configuration['password'],
            'The tenant would be handed a password its database was not created with.'
        );
    }

    /**
     * @test
     */
    public function the_prefix_mode_changes_nothing_but_the_prefix()
    {
        $system = $this->systemConfiguration();
        $configuration = $this->configurationFor(Connection::DIVISION_MODE_SEPARATE_PREFIX);

        $this->assertSame("{$this->website->id}_", $configuration['prefix']);

        // The tenant shares the system database in this mode; the prefix is
        // the only thing telling its tables apart.
        $this->assertSame($system['database'], $configuration['database']);
        $this->assertSame($system['username'], $configuration['username']);
    }

    /**
     * @test
     */
    public function the_schema_mode_points_the_search_path_at_the_uuid()
    {
        $configuration = $this->configurationFor(Connection::DIVISION_MODE_SEPARATE_SCHEMA);

        $this->assertSame($this->website->uuid, $configuration['schema']);
        $this->assertSame($this->website->uuid, $configuration['search_path']);
        $this->assertSame($this->website->uuid, $configuration['username']);
        $this->assertSame(
            app(PasswordGenerator::class)->generate($this->website),
            $configuration['password']
        );
    }

    /**
     * @test
     */
    public function the_bypass_mode_hands_over_the_system_configuration_untouched()
    {
        $system = $this->systemConfiguration();
        $configuration = $this->configurationFor(Connection::DIVISION_MODE_BYPASS);

        $this->assertSame(
            $system,
            Arr::except($configuration, 'uuid'),
            'Bypass leaves the configuration to the application; the package must not shape it.'
        );
    }

    /**
     * Every mode stamps the uuid, and Connection::set() compares it to decide
     * whether the open connection still belongs to the tenant being set.
     *
     * @test
     */
    public function every_mode_stamps_the_tenant_uuid()
    {
        foreach ([
            Connection::DIVISION_MODE_SEPARATE_DATABASE,
            Connection::DIVISION_MODE_SEPARATE_PREFIX,
            Connection::DIVISION_MODE_SEPARATE_SCHEMA,
            Connection::DIVISION_MODE_BYPASS,
        ] as $mode) {
            $this->assertSame(
                $this->website->uuid,
                $this->configurationFor($mode)['uuid'],
                "Division mode '$mode' left the configuration unstamped."
            );
        }
    }

    /**
     * @test
     */
    public function an_unknown_division_mode_is_refused()
    {
        $this->expectException(ConnectionException::class);

        $this->configurationFor('every-tenant-in-one-heap');
    }

    /**
     * @test
     */
    public function the_tenant_configuration_is_empty_until_a_tenant_is_set()
    {
        $this->assertSame([], $this->connection->configuration());
        $this->assertFalse($this->connection->exists());

        $this->connection->set($this->website);

        $this->assertSame($this->website->uuid, Arr::get($this->connection->configuration(), 'uuid'));
        $this->assertTrue($this->connection->exists());

        $this->connection->purge();

        $this->assertSame([], $this->connection->configuration());
    }

    /**
     * Build the tenant configuration under a given division mode, leaving the
     * mode the suite runs under untouched.
     */
    protected function configurationFor(string $mode, Website $website = null): array
    {
        $previous = config('tenancy.db.tenant-division-mode');

        config(['tenancy.db.tenant-division-mode' => $mode]);

        try {
            return $this->connection->generateConfigurationArray($website ?? $this->website);
        } finally {
            config(['tenancy.db.tenant-division-mode' => $previous]);
        }
    }

    /**
     * Run a callback with the connection names overridden, putting the real
     * ones back afterwards: teardown reaches for the tenant connection by
     * name, and a leftover override would take the tenant databases with it.
     */
    protected function withConnectionNames($tenant, $system, callable $callback): void
    {
        $previous = [
            'tenancy.db.tenant-connection-name' => config('tenancy.db.tenant-connection-name'),
            'tenancy.db.system-connection-name' => config('tenancy.db.system-connection-name'),
        ];

        config([
            'tenancy.db.tenant-connection-name' => $tenant,
            'tenancy.db.system-connection-name' => $system,
        ]);

        try {
            $callback();
        } finally {
            config($previous);
        }
    }

    protected function systemConfiguration(): array
    {
        return config('database.connections.'.$this->connection->systemName());
    }

    protected function duringSetUp(Application $app)
    {
        $this->setUpHostnames(true);
        $this->setUpWebsites(true);
    }
}
