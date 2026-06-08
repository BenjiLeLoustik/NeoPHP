<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests;

use Neo\Core\DI\Container;
use Neo\Core\Extension\Array\ArrayExtension;
use Neo\Core\Extension\Date\DateExtension;
use Neo\Core\Extension\ExtensionModule;
use Neo\Core\Extension\ExtensionViewExtension;
use Neo\Core\Extension\File\FileExtension;
use Neo\Core\Extension\Html\HtmlExtension;
use Neo\Core\Extension\Json\JsonExtension;
use Neo\Core\Extension\Number\NumberExtension;
use Neo\Core\Extension\Path\PathExtension;
use Neo\Core\Extension\String\StringExtension;
use Neo\Core\Extension\Url\UrlExtension;
use Neo\Core\View\ViewModule;
use PHPUnit\Framework\TestCase;

class ExtensionModuleTest extends TestCase
{
    public function testDependenciesReturnViewModule(): void
    {
        $module = new ExtensionModule();
        self::assertSame([ViewModule::class], $module->dependencies());
    }

    public function testRegisterConfiguresAllExtensionsAndTwigBinding(): void
    {
        $container = new Container();
        $module = new ExtensionModule();

        $module->register($container);

        self::assertTrue($container->has(StringExtension::class));
        self::assertTrue($container->has(ArrayExtension::class));
        self::assertTrue($container->has(NumberExtension::class));
        self::assertTrue($container->has(DateExtension::class));
        self::assertTrue($container->has(FileExtension::class));
        self::assertTrue($container->has(JsonExtension::class));
        self::assertTrue($container->has(UrlExtension::class));
        self::assertTrue($container->has(PathExtension::class));
        self::assertTrue($container->has(HtmlExtension::class));

        self::assertTrue($container->has(ExtensionViewExtension::class));

        $taggedServices = $container->tagged('twig.extension');

        $isFound = false;
        foreach ($taggedServices as $serviceInstance) {
            if ($serviceInstance instanceof ExtensionViewExtension) {
                $isFound = true;
                break;
            }
        }

        self::assertTrue($isFound, "L'instance d'ExtensionViewExtension n'a pas été trouvée dans les services taggués 'twig.extension'.");
    }
}