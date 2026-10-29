<?php

namespace Amyla\Worlddata\Tests;

use Amyla\Worlddata\WorlddataServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

final class DiscoveryTest extends TestCase
{
    public function test_a_consuming_application_discovers_the_provider_from_composer_metadata(): void
    {
        $directory = sys_get_temp_dir().'/worlddata-discovery-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($directory.'/vendor/composer', 0777, true);
        $files->makeDirectory($directory.'/bootstrap/cache', 0777, true);
        $composer = $files->json(__DIR__.'/../composer.json');
        $files->put($directory.'/composer.json', '{}');
        $files->put($directory.'/vendor/composer/installed.json', json_encode(['packages' => [$composer]], JSON_THROW_ON_ERROR));
        $previousContainer = Container::getInstance();
        $previousFacade = Facade::getFacadeApplication();
        try {
            $app = new Application($directory);
            $app->instance('config', new Repository(['app' => ['providers' => [], 'locale' => 'de', 'fallback_locale' => 'en']]));
            $app->instance(PackageManifest::class, new PackageManifest($files, $directory, $directory.'/bootstrap/cache/packages.php'));
            Facade::setFacadeApplication($app);
            $app->registerConfiguredProviders();

            $this->assertTrue($app->providerIsLoaded(WorlddataServiceProvider::class));
            $this->assertSame('Deutschland', world()->countries()->findOrFail('DE')->name);
            $this->assertFileExists($directory.'/bootstrap/cache/packages.php');
        } finally {
            Container::setInstance($previousContainer);
            Facade::setFacadeApplication($previousFacade);
            $files->deleteDirectory($directory);
        }
    }
}
