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

namespace Hyn\Tenancy\Tests\Filesystem;

use Hyn\Tenancy\Contracts\Website\UuidGenerator;
use Hyn\Tenancy\Tests\TestCase;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;

class DirectoryGeneratorTest extends TestCase
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    protected function duringSetUp(Application $app)
    {
        config(['tenancy.website.auto-delete-tenant-directory' => true]);

        $this->setUpWebsites(true);

        $this->filesystem = app('tenancy.disk');
    }

    #[Test]
    public function directory_created()
    {
        $this->assertTrue(config('tenancy.website.auto-create-tenant-directory'));

        $this->assertTrue(
            $this->filesystem->exists($this->website->uuid),
            "Failed to generate directory for website {$this->website->uuid}."
        );
    }

    #[Test]
    #[Depends('directory_created')]
    public function directory_modified()
    {
        $this->website->uuid = app(UuidGenerator::class)->generate($this->website);

        $this->website = $this->websites->update($this->website);

        $this->assertTrue($this->filesystem->exists($this->website->uuid));
    }

    #[Test]
    #[Depends('directory_modified')]
    public function directory_deleted()
    {
        $this->websites->delete($this->website);

        $this->assertFalse($this->filesystem->exists($this->website->uuid));
    }
}
