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

namespace Hyn\Tenancy\Database\Console\Migrations;

use Hyn\Tenancy\Traits\MutatesMigrationCommands;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Console\Migrations\MigrateCommand as BaseCommand;
use Illuminate\Database\Migrations\Migrator;
use Throwable;

class MigrateCommand extends BaseCommand
{
    use MutatesMigrationCommands;

    public function __construct(Migrator $migrator, Dispatcher $dispatcher)
    {
        parent::__construct($migrator, $dispatcher);

        $this->bootTenancy();
    }

    /**
     * Refuse to create the database the migration failed to reach.
     *
     * A tenant database that is missing is a provisioning failure, and
     * creating one leaves the tenant connectable but empty.
     */
    protected function handleMissingDatabase(Throwable $e)
    {
        return false;
    }
}
