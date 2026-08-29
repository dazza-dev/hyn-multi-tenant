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

namespace Hyn\Tenancy\Tests\Models;

use Hyn\Tenancy\Tests\Extend\SystemExtend;
use Hyn\Tenancy\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SystemModelTest extends TestCase
{
    #[Test]
    public function uses_correct_connection()
    {
        $model = new SystemExtend();

        $this->assertEquals($model->getConnectionName(), $this->connection->systemName());
        $this->assertEquals($model->getConnection(), $this->connection->system());
    }
}
