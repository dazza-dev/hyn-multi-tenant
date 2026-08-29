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

use Hyn\Tenancy\Tests\TestCase;
use Hyn\Tenancy\Website\Directory;
use Illuminate\Contracts\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;

class LoadsVendorTest extends TestCase
{
    /**
     * @var Directory
     */
    protected $directory;

    protected function duringSetUp(Application $app)
    {
        $this->setUpHostnames(true);
        $this->setUpWebsites(true, true);

        $this->directory = $app->make(Directory::class);
        $this->directory->setWebsite($this->website);
    }

    #[Test]
    public function reads_additional_vendor()
    {
        // Directory should now exists, let's write the config folder.
        $this->assertTrue($this->directory->makeDirectory('vendor'));

        // Write a testing config.
        $this->assertTrue($this->directory->put(
            'vendor/autoload.php',
            <<<EOM
<?php

namespace Test\Vendor;

class Foo {}
EOM
        ));

        $this->assertTrue($this->directory->exists('vendor/autoload.php'));

        $this->activateTenant();

        $this->assertTrue(class_exists(\Test\Vendor\Foo::class));
    }
}
