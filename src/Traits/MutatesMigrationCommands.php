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

namespace Hyn\Tenancy\Traits;

use InvalidArgumentException;
use Hyn\Tenancy\Database\Connection;
use Hyn\Tenancy\Contracts\Repositories\WebsiteRepository;

trait MutatesMigrationCommands
{
    use AddWebsiteFilterOnCommand;
    /**
     * @var WebsiteRepository
     */
    private $websites;
    /**
     * @var Connection
     */
    private $connection;

    /**
     * Call from the constructor of each command, after parent::__construct().
     */
    protected function bootTenancy(): void
    {
        $this->setName('tenancy:' . $this->getName());

        $this->defineWebsiteOption();

        $this->websites = app(WebsiteRepository::class);
        $this->connection = app(Connection::class);
    }

    public function handle()
    {
        if ($this->refusesToRun()) {
            return self::FAILURE;
        }

        $this->input->setOption('force', true);
        $this->input->setOption('database', $this->connection->tenantName());

        $this->processHandle();
    }

    /**
     * Whether the command must not run.
     *
     * --graceful turns a failed migration into a successful exit code, which
     * across a loop of tenants hides which of them are left unmigrated.
     */
    protected function refusesToRun(): bool
    {
        if ($this->input->hasOption('graceful') && $this->option('graceful')) {
            $this->components->error('--graceful is not available for tenant migrations.');

            return true;
        }

        return $this->prohibited() || ! $this->confirmToProceed();
    }

    /**
     * Whether the environment prohibits this command.
     *
     * Of the commands mutated here, migrate is the one the framework does not
     * make prohibitable.
     */
    protected function prohibited(): bool
    {
        return method_exists($this, 'isProhibited') && $this->isProhibited();
    }

    /**
     * Get the path to the migration directory.
     *
     * @return string
     */
    protected function getMigrationPaths()
    {
        if ($this->input->hasOption('path') && $this->option('path')) {
            return parent::getMigrationPaths();
        }

        // Tenant migrations path is configured.
        if (($path = config('tenancy.db.tenant-migrations-path')) && ! empty($path)) {
            return (array) $path;
        }

        throw new InvalidArgumentException("To prevent unwanted migrations from database/migrations, always specify a path.");
    }

}
