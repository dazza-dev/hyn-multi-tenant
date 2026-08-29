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

use Hyn\Tenancy\Database\Console\Migrations\RefreshCommand;
use Hyn\Tenancy\Models\Website;
use Hyn\Tenancy\Tests\Seeds\SampleSeeder;
use PHPUnit\Framework\Attributes\Test;

class RefreshCommandTest extends DatabaseCommandTestCase
{
    #[Test]
    public function is_ioc_bound()
    {
        $this->assertInstanceOf(
            RefreshCommand::class,
            $this->app->make(RefreshCommand::class)
        );
    }

    #[Test]
    public function runs_refresh_on_tenants()
    {
        $this->migrateAndTest('migrate');

        $this->migrateAndTest('migrate:refresh', function (Website $website) {
            $this->connection->set($website);
            $this->assertTrue(
                $this->connection->get()->getSchemaBuilder()->hasTable('samples'),
                "Connection for {$website->uuid} has no table samples"
            );
        });
    }

    /**
     * Seeding has to honour the same tenant filter the rest of the command does.
     */
    #[Test]
    public function runs_refresh_with_seeding_on_selected_tenant()
    {
        $otherWebsite = $this->getReplicatedWebsite();

        $this->migrateAndTest('migrate');

        $this->migrateAndTest('migrate:refresh', null, null, [
            '--website_id' => [$this->website->id],
            '--seed' => 1,
            '--seeder' => SampleSeeder::class,
        ]);

        $this->connection->set($this->website);
        $this->assertEquals(
            2,
            $this->connection->get()->table('samples')->count(),
            "Tenant {$this->website->uuid} was asked to be seeded and was not."
        );

        $this->connection->set($otherWebsite);
        $this->assertEquals(
            0,
            $this->connection->get()->table('samples')->count(),
            "Tenant {$otherWebsite->uuid} was seeded without being asked for."
        );
    }

    /**
     * Passing the filter through must not turn "no filter" into "no tenants".
     */
    #[Test]
    public function runs_refresh_with_seeding_on_every_tenant_when_none_is_named()
    {
        $otherWebsite = $this->getReplicatedWebsite();

        $this->migrateAndTest('migrate');

        $this->migrateAndTest('migrate:refresh', null, null, [
            '--seed' => 1,
            '--seeder' => SampleSeeder::class,
        ]);

        foreach ([$this->website, $otherWebsite] as $website) {
            $this->connection->set($website);
            $this->assertEquals(
                2,
                $this->connection->get()->table('samples')->count(),
                "Tenant {$website->uuid} was skipped although no filter was given."
            );
        }
    }
}
