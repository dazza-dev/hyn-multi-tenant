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
use Illuminate\Database\Console\Migrations\RollbackCommand as BaseCommand;
use Illuminate\Database\Migrations\Migrator;

class RollbackCommand extends BaseCommand
{
    use MutatesMigrationCommands;

    public function __construct(Migrator $migrator)
    {
        parent::__construct($migrator);

        $this->bootTenancy();
    }
}
