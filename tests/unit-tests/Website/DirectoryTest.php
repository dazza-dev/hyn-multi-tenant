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

namespace Hyn\Tenancy\Tests\Website;

use Hyn\Tenancy\Tests\Test;
use Hyn\Tenancy\Website\Directory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem as LocalSystem;
use Mockery;

class DirectoryTest extends Test
{
    /**
     * @var Directory
     */
    protected $directory;

    protected function duringSetUp(Application $app)
    {
        $this->directory = $app->make(Directory::class);
    }

    /**
     * @test
     */
    public function can_switch_website()
    {
        $this->setUpWebsites(true);

        $this->assertNull($this->directory->getWebsite());

        $this->directory->setWebsite($this->website);

        $this->assertEquals($this->website, $this->directory->getWebsite());
    }

    /**
     * The parameter stays untyped on purpose: from Laravel 11 the Filesystem
     * contract declares path($path), and typing it here is a fatal error.
     *
     * @test
     */
    public function path_prefixes_with_the_tenant_and_takes_no_argument_types()
    {
        $this->setUpWebsites(true);
        $this->directory->setWebsite($this->website);

        $uuid = $this->website->uuid;

        $this->assertSame("$uuid/", $this->directory->path());
        $this->assertSame("$uuid/uploads/a.txt", $this->directory->path('uploads/a.txt'));

        // Already prefixed paths are left alone rather than prefixed twice.
        $this->assertSame("$uuid/uploads", $this->directory->path("$uuid/uploads"));
    }

    /**
     * @test
     */
    public function put_file_lands_inside_the_tenants_directory()
    {
        $this->setUpWebsites(true);
        $uuid = $this->website->uuid;

        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('putFile')->once()
            ->with("$uuid/uploads", 'the-file', [])
            ->andReturn("$uuid/uploads/generated.txt");

        $directory = new Directory($filesystem, $this->app['config'], $this->app[LocalSystem::class]);
        $directory->setWebsite($this->website);

        $this->assertSame("$uuid/uploads/generated.txt", $directory->putFile('uploads', 'the-file'));
    }

    /**
     * @test
     */
    public function put_file_as_lands_inside_the_tenants_directory()
    {
        $this->setUpWebsites(true);
        $uuid = $this->website->uuid;

        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('putFileAs')->once()
            ->with("$uuid/uploads", 'the-file', 'named.txt', [])
            ->andReturn("$uuid/uploads/named.txt");

        $directory = new Directory($filesystem, $this->app['config'], $this->app[LocalSystem::class]);
        $directory->setWebsite($this->website);

        $this->assertSame("$uuid/uploads/named.txt", $directory->putFileAs('uploads', 'the-file', 'named.txt'));
    }
}
