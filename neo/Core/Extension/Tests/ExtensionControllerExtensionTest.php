<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests;

use Neo\Core\Controller\AbstractController;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Array\ArrayExtension;
use Neo\Core\Extension\Date\DateExtension;
use Neo\Core\Extension\ExtensionControllerExtension;
use Neo\Core\Extension\File\FileExtension;
use Neo\Core\Extension\Html\HtmlExtension;
use Neo\Core\Extension\Json\JsonExtension;
use Neo\Core\Extension\Number\NumberExtension;
use Neo\Core\Extension\Path\PathExtension;
use Neo\Core\Extension\String\StringExtension;
use Neo\Core\Extension\Url\UrlExtension;
use PHPUnit\Framework\TestCase;

class ExtensionControllerExtensionTest extends TestCase
{
    public function testExtendRegistersAllExtensionMethodsOnController(): void
    {
        $container = new Container();

        $stringExt = new StringExtension();
        $arrayExt = new ArrayExtension();
        $dateExt = new DateExtension();
        $fileExt = new FileExtension();
        $htmlExt = new HtmlExtension();
        $jsonExt = new JsonExtension();
        $numberExt = new NumberExtension();
        $pathExt = new PathExtension();
        $urlExt = new UrlExtension();

        $container->set(StringExtension::class, fn() => $stringExt);
        $container->set(ArrayExtension::class, fn() => $arrayExt);
        $container->set(DateExtension::class, fn() => $dateExt);
        $container->set(FileExtension::class, fn() => $fileExt);
        $container->set(HtmlExtension::class, fn() => $htmlExt);
        $container->set(JsonExtension::class, fn() => $jsonExt);
        $container->set(NumberExtension::class, fn() => $numberExt);
        $container->set(PathExtension::class, fn() => $pathExt);
        $container->set(UrlExtension::class, fn() => $urlExt);

        $controller = new class extends AbstractController {
            private array $methods = [];

            public function registerMethod(string $name, callable|\Closure $resolver): void
            {
                $this->methods[$name] = $resolver;
            }

            public function callRegisteredMethod(string $name): mixed
            {
                return isset($this->methods[$name]) ? $this->methods[$name]() : null;
            }

            public function hasRegisteredMethod(string $name): bool
            {
                return isset($this->methods[$name]);
            }
        };

        $extension = new ExtensionControllerExtension();
        $extension->extend($controller, $container);

        self::assertTrue($controller->hasRegisteredMethod('getString'));
        self::assertTrue($controller->hasRegisteredMethod('getArray'));
        self::assertTrue($controller->hasRegisteredMethod('getDate'));
        self::assertTrue($controller->hasRegisteredMethod('getFile'));
        self::assertTrue($controller->hasRegisteredMethod('getHtml'));
        self::assertTrue($controller->hasRegisteredMethod('getJson'));
        self::assertTrue($controller->hasRegisteredMethod('getNumber'));
        self::assertTrue($controller->hasRegisteredMethod('getPath'));
        self::assertTrue($controller->hasRegisteredMethod('getUrl'));

        self::assertSame($stringExt, $controller->callRegisteredMethod('getString'));
        self::assertSame($arrayExt, $controller->callRegisteredMethod('getArray'));
        self::assertSame($htmlExt, $controller->callRegisteredMethod('getHtml'));
    }
}