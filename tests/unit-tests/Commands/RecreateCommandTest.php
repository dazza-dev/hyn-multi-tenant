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

use Hyn\Tenancy\Models\Website;
use Hyn\Tenancy\Traits\DispatchesEvents;
use Illuminate\Contracts\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

class RecreateCommandTest extends DatabaseCommandTestCase
{
    use DispatchesEvents;

    protected function duringSetUp(Application $app)
    {
        $this->cleanupTenancy();

        parent::duringSetUp($app);
    }

    #[Test]
    public function can_recreate_deleted_tenant_database()
    {
        config([
            'tenancy.db.tenant-migrations-path' => __DIR__ . '/../../migrations'
        ]);

        $idBeforeDelete = $this->website->id;

        $this->connection->migrate($this->website, __DIR__ . '/../../migrations');

        $this->connection->set($this->website);

        $this->assertTrue($this->connection->get()->getSchemaBuilder()->hasTable('migrations'));

        $this->websites->delete($this->website, true);

        $this->assertFalse($this->website->exists);

        // Save the website instance to the database.
        $this->website->save();

        try {
            if (!$this->assertFalse($this->connection->get()->getSchemaBuilder()->hasTable('migrations'))) {
                $this->fail('`migrations` table in tenant db still exists.');
            }
        } catch (\Exception $e) {
            // Surpress exception
        }

        $this->artisan('tenancy:recreate');

        $this->connection->set($this->website);

        try {
            $reachable = $this->connection->get()->getSchemaBuilder()->hasTable('migrations');
        } catch (Throwable $e) {
            $this->fail($this->stateOf($this->website, $idBeforeDelete) . "\n" . $e->getMessage());
        }

        $this->assertTrue($reachable, $this->stateOf($this->website, $idBeforeDelete));
    }

    /**
     * What the system database says about the tenant, for a failure to name
     * rather than leave to guesswork.
     */
    protected function stateOf(Website $website, int $idBeforeDelete): string
    {
        $system = $this->connection->system();
        $driver = $system->getDriverName();

        if ($driver === 'pgsql') {
            $user = $system->table('pg_roles')->where('rolname', $website->uuid)->count();
            $database = $system->table('pg_database')->where('datname', $website->uuid)->count();
        } else {
            $user = count($system->select('SELECT user FROM mysql.user WHERE user = ?', [$website->uuid]));
            $database = count($system->select("SHOW DATABASES LIKE '{$website->uuid}'"));
        }

        return sprintf(
            'tenant %s on %s: user %s, database %s, website id %d before the delete and %d after',
            $website->uuid,
            $driver,
            $user ? 'exists' : 'gone',
            $database ? 'exists' : 'gone',
            $idBeforeDelete,
            $website->id
        );
    }
}
