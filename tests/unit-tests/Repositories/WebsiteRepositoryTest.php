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

namespace Hyn\Tenancy\Tests\Repositories;

use Hyn\Tenancy\Tests\Test;
use Illuminate\Contracts\Foundation\Application;

class WebsiteRepositoryTest extends Test
{
    /**
     * @test
     */
    public function creates_website()
    {
        $this->websites->create($this->website);

        $this->assertTrue($this->website->exists);
    }

    /**
     * @test
     */
    public function updates_website()
    {
        $this->setUpWebsites(true);

        $saved = $this->websites->update($this->website);

        $this->assertEquals($this->website->id, $saved->id);
    }

    /**
     * @test
     * @depends creates_website
     */
    public function deletes_website()
    {
        $this->setUpWebsites(true);

        $this->websites->delete($this->website);

        $this->assertTrue($this->website->exists);
        $this->assertNotNull($this->website->deleted_at);

        $this->websites->delete($this->website, true);

        $this->assertFalse($this->website->exists);
    }

    /**
     * @test
     */
    public function setting_custom_uuid()
    {
        $this->website->uuid = 'foo';

        $website = $this->websites->create($this->website);

        $this->assertEquals('foo', $website->uuid);
    }

    /**
     * The uuid becomes the tenant's database name, and MySQL caps identifiers
     * at 64 characters. Its shape is not cosmetic.
     *
     * @test
     */
    public function a_created_website_is_given_a_uuid_of_the_configured_shape()
    {
        $this->websites->create($this->website);

        if (config('tenancy.website.uuid-limit-length-to-32')) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $this->website->uuid);
        } else {
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
                $this->website->uuid
            );
        }
    }

    /**
     * @test
     */
    public function the_uuid_survives_an_update()
    {
        $this->websites->create($this->website);

        $uuid = $this->website->uuid;

        $this->website->managed_by_database_connection = null;
        $this->websites->update($this->website);

        $this->assertSame(
            $uuid,
            $this->website->fresh()->uuid,
            'A regenerated uuid would point the tenant at a database that does not exist.'
        );
    }

    /**
     * @test
     */
    public function renaming_the_uuid_forgets_the_website_under_the_old_one()
    {
        $this->websites->create($this->website);

        $old = $this->website->uuid;

        // Warm the cache under the old uuid.
        $this->assertNotNull($this->websites->findByUuid($old));

        $this->website->uuid = 'renamed'.substr($old, 0, 20);
        $this->websites->update($this->website);

        $this->assertNull(
            $this->websites->findByUuid($old),
            'The website is still reachable under the uuid it no longer has.'
        );
        $this->assertNotNull($this->websites->findByUuid($this->website->uuid));
    }

    protected function duringSetUp(Application $app)
    {
        $this->setUpWebsites();
        $this->setUpHostnames();
    }
}
