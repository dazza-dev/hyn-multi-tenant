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

namespace Hyn\Tenancy\Tests\Commands;

use Hyn\Tenancy\Database\Connection;
use Hyn\Tenancy\Database\Console\Migrations\MigrateCommand;
use Hyn\Tenancy\Models\Website;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

class MigrateCommandTest extends DatabaseCommandTestCase
{
    #[Test]
    public function is_ioc_bound()
    {
        $this->assertInstanceOf(
            MigrateCommand::class,
            $this->app->make(MigrateCommand::class)
        );
    }

    #[Test]
    public function runs_migrate_on_one_tenant()
    {
        /** @var Website $otherWebsite */
        $otherWebsite = $this->getReplicatedWebsite();

        $this->connection->migrate($this->website, __DIR__ . '/../../migrations');

        $this->connection->set($this->website);

        $this->assertTrue($this->connection->get()->getSchemaBuilder()->hasTable('samples'));

        $this->connection->set($otherWebsite);

        $this->assertFalse($this->connection->get()->getSchemaBuilder()->hasTable('samples'));
    }

    #[Test]
    public function runs_migrate_on_one_tenant_by_configuration()
    {
        /** @var Website $otherWebsite */
        $otherWebsite = $this->getReplicatedWebsite();

        config(['tenancy.db.tenant-migrations-path' => realpath(__DIR__ . '/../../migrations')]);

        $this->connection->migrate($this->website);

        $this->connection->set($this->website);

        $this->assertTrue($this->connection->get()->getSchemaBuilder()->hasTable('samples'));

        $this->connection->set($otherWebsite);

        $this->assertFalse($this->connection->get()->getSchemaBuilder()->hasTable('samples'));
    }

    #[Test]
    public function runs_migrate_on_tenants()
    {
        $this->migrateAndTest('migrate', function (Website $website) {
            $this->connection->set($website);

            $this->assertTrue(
                $this->connection->get()->getSchemaBuilder()->hasTable('samples'),
                "Connection for {$website->uuid} has no table samples"
            );
        });
    }

    #[Test]
    public function refuses_the_graceful_option()
    {
        $this->setUpWebsites(true);

        $code = $this->artisan('tenancy:migrate', [
            '--website_id' => [$this->website->id],
            '--graceful' => true,
            '--realpath' => true,
            '--path' => __DIR__ . '/../../migrations',
            '--force' => 1,
            '--no-interaction' => 1,
        ]);

        $this->assertEquals(1, $code);
    }

    /**
     * A tenant database that is missing means provisioning failed, and the
     * command has to say so rather than carry on.
     */
    #[Test]
    public function a_missing_tenant_database_makes_the_command_fail()
    {
        if (config('tenancy.db.tenant-division-mode') !== Connection::DIVISION_MODE_SEPARATE_DATABASE) {
            $this->markTestSkipped('Only the database division mode gives a tenant a database of its own.');
        }

        $this->setUpWebsites(true);

        $this->dropDatabaseOf($this->website);

        $this->expectException(QueryException::class);

        $this->artisan('tenancy:migrate', [
            '--website_id' => [$this->website->id],
            '--realpath' => true,
            '--path' => __DIR__ . '/../../migrations',
            '--force' => 1,
            '--no-interaction' => 1,
        ]);
    }

    protected function dropDatabaseOf(Website $website): void
    {
        $this->connection->purge();

        $system = $this->connection->system();

        $name = $system->getDriverName() === 'pgsql'
            ? '"' . $website->uuid . '"'
            : '`' . $website->uuid . '`';

        $system->statement("DROP DATABASE IF EXISTS $name");
    }

    /**
     * Without a path the migrator would run database/migrations, which belongs
     * to the system database, against every tenant. The exception is the
     * guard, not an oversight.
     */
    #[Test]
    public function refuses_to_migrate_without_a_path()
    {
        config(['tenancy.db.tenant-migrations-path' => null]);

        $this->expectException(InvalidArgumentException::class);

        $this->artisan('tenancy:migrate', [
            '--website_id' => [$this->website->id],
            '--force' => 1,
            '--no-interaction' => 1,
        ]);
    }

    #[Test]
    public function purges_connection_after_running_migrate_on_multiple_tenants()
    {
        $website = new Website();
        $this->websites->create($website);

        $this->assertEquals(2, $this->websites->query()->count());

        $connection = $this->swapConnectionWithSpy();
        $this->reloadArtisanCommand(MigrateCommand::class);

        $this->migrateAndTest('migrate');

        $connection->shouldHaveReceived('purge')->twice();
    }

    #[Test]
    public function does_not_purge_connection_after_running_migrate_on_one_tenant()
    {
        $connection = $this->swapConnectionWithSpy();
        $this->reloadArtisanCommand(MigrateCommand::class);

        $this->migrateAndTest('migrate');

        $connection->shouldNotHaveReceived('purge');
    }
}
