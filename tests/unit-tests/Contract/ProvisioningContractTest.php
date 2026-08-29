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
use Hyn\Tenancy\Tests\TestCase;
use Throwable;
use PHPUnit\Framework\Attributes\Test;

/**
 * What creating and deleting a website does to the storage behind it.
 *
 * Every other test leans on provisioning working; none of them notices when
 * teardown quietly stops removing what it made.
 */
class ProvisioningContractTest extends TestCase
{
    #[Test]
    public function creating_a_tenant_provisions_storage_it_can_reach()
    {
        $website = new Website();

        $this->websites->create($website);

        $this->assertTrue(
            $this->databaseIsReachable($website),
            "Tenant {$website->uuid} was created without storage to connect to."
        );
    }

    #[Test]
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

    /**
     * The password is derived per website, so a database user surviving from
     * an earlier tenant of the same name holds one that no longer opens
     * anything. Provisioning has to set it, not assume it.
     */
    #[Test]
    public function provisioning_over_a_surviving_database_user_still_connects()
    {
        $this->skipUnlessTenantsOwnADatabase();

        $website = new Website();
        $this->websites->create($website);

        $uuid = $website->uuid;

        // Delete the tenant but leave its user behind, which is what a failed
        // drop leaves in place.
        config(['tenancy.db.auto-delete-tenant-database-user' => false]);

        $this->websites->delete($website, true);

        config(['tenancy.db.auto-delete-tenant-database-user' => true]);

        // Same uuid, new row: same user name, different password.
        $again = Website::unguarded(function () use ($uuid) {
            return new Website(['uuid' => $uuid]);
        });

        $this->websites->create($again);

        $this->assertEquals($uuid, $again->uuid);

        $this->assertTrue(
            $this->databaseIsReachable($again),
            "Tenant $uuid was provisioned over a surviving user and cannot connect."
        );
    }

    protected function databaseIsReachable(Website $website): bool
    {
        try {
            $this->connection->set($website);

            // Any schema query will do; it only has to reach the tenant's storage.
            $this->connection->get()->getSchemaBuilder()->hasTable('a_table_no_tenant_has');

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
