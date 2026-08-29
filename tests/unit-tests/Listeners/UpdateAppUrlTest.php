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

namespace Hyn\Tenancy\Tests\Listeners;

use Hyn\Tenancy\Tests\TestCase;
use Illuminate\Contracts\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;

class UpdateAppUrlTest extends TestCase
{
    protected function duringSetUp(Application $app)
    {
        config(['tenancy.hostname.update-app-url' => true]);
        $this->setUpHostnames(true);
        $this->setUpWebsites(true, true);
    }

    #[Test]
    public function tenant_url_used()
    {
        $url = config('app.url');
        $this->assertEquals($url, url()->to('/'));

        $this->activateTenant();

        $this->assertEquals('http://'.$this->hostname->fqdn, config('app.url'));
        $this->assertEquals('http://'.$this->hostname->fqdn, url()->to('/'));
    }
}
