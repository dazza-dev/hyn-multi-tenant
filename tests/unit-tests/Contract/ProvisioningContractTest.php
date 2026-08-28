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

use Hyn\Tenancy\Database\Connection;
use Hyn\Tenancy\Models\Website;
use Hyn\Tenancy\Tests\Test;
use Throwable;

/**
 * What creating and deleting a website does to the storage behind it.
 *
 * Every other test leans on provisioning working; none of them notices when
 * teardown quietly stops removing what it made.
 */
class ProvisioningContractTest extends Test
{
    /**
     * @test
     */
    public function creating_a_tenant_provisions_storage_it_can_reach()
    {
        $website = new Website();

        $this->websites->create($website);

        $this->connection->set($website);

        $this->assertIsArray(
            $this->connection->get()->getSchemaBuilder()->getTableListing(),
            "Tenant {$website->uuid} was created without storage to connect to."
        );

        $this->connection->purge();
    }

    /**
     * @test
     */
    public function deleting_a_tenant_takes_its_database_with_it()
    {
        $this->skipUnlessTenantsOwnADatabase();

        if (! config('tenancy.db.auto-delete-tenant-database')) {
            $this->markTestSkipped('Tenant databases are configured to outlive their website.');
        }

        $website = new Website();
        $this->websites->create($website);

        $uuid = $website->uuid;

        // Both directions through the same helper, so a helper that can only
        // answer "no" cannot pass this test.
        $this->assertTrue($this->databaseIsReachable($website));

        $this->websites->delete($website, true);

        $this->assertFalse(
            $this->databaseIsReachable($website),
            "The database of tenant $uuid outlived the website. Deleted tenants leave their data readable to whoever gets the name next."
        );
    }

    protected function databaseIsReachable(Website $website): bool
    {
        try {
            $this->connection->set($website);
            $this->connection->get()->getSchemaBuilder()->getTableListing();

            return true;
        } catch (Throwable $e) {
            return false;
        } finally {
            $this->connection->purge();
        }
    }

    protected function skipUnlessTenantsOwnADatabase(): void
    {
        if (config('tenancy.db.tenant-division-mode') !== Connection::DIVISION_MODE_SEPARATE_DATABASE) {
            $this->markTestSkipped('Only the database division mode gives a tenant a database of its own.');
        }
    }
}
