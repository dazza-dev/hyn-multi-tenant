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

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Tests\Test;
use Illuminate\Contracts\Foundation\Application;

/**
 * The observable behaviour of Hyn\Tenancy\Environment.
 *
 * This is the entry point applications actually use — Tenancy::website(),
 * Tenancy::tenant() — so its answers are part of the contract even where the
 * shape of them is awkward.
 */
class EnvironmentContractTest extends Test
{
    /** @var Environment */
    protected $environment;

    /**
     * @test
     */
    public function no_tenant_is_active_by_default()
    {
        $this->assertNull($this->environment->tenant());
    }

    /**
     * @test
     */
    public function setting_the_tenant_makes_it_the_active_one()
    {
        $returned = $this->environment->tenant($this->website);

        $this->assertTrue($this->website->is($returned));
        $this->assertTrue($this->website->is($this->environment->tenant()));
    }

    /**
     * @test
     */
    public function forgetting_the_tenant_leaves_none_active()
    {
        $this->environment->tenant($this->website);

        $this->environment->forgetTenant();

        $this->assertNull($this->environment->tenant());
    }

    /**
     * A reading of tenant(null) as "release the tenant" is wrong, and quiet
     * about it. forgetTenant() is the one that releases.
     *
     * @test
     */
    public function passing_null_reads_the_tenant_rather_than_releasing_it()
    {
        $this->environment->tenant($this->website);

        $this->assertTrue(
            $this->website->is($this->environment->tenant(null)),
            'tenant(null) is a getter; releasing the tenant is forgetTenant().'
        );
    }

    /**
     * @test
     */
    public function setting_the_hostname_makes_it_the_current_one()
    {
        $returned = $this->environment->hostname($this->hostname);

        $this->assertTrue($this->hostname->is($returned));
        $this->assertTrue($this->hostname->is($this->environment->hostname()));
    }

    /**
     * @test
     */
    public function the_website_is_derived_from_the_current_hostname()
    {
        $this->hostnames->attach($this->hostname, $this->website);

        $this->environment->hostname($this->hostname);

        $this->assertTrue($this->website->is($this->environment->website()));
    }

    /**
     * @test
     */
    public function there_is_no_website_while_no_hostname_is_current()
    {
        $this->assertNull($this->environment->website());
    }

    /**
     * @test
     */
    public function the_environment_reports_tenancy_as_installed()
    {
        $this->assertTrue(
            $this->environment->installed(),
            'The system tables are migrated, so the package must report itself installed.'
        );
    }

    protected function duringSetUp(Application $app)
    {
        $this->environment = $app->make(Environment::class);

        $this->setUpHostnames(true);
        $this->setUpWebsites(true);
    }
}
