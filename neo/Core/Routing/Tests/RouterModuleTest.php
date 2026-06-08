<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Tests;

use Neo\Core\DI\Container;
use Neo\Core\Routing\Router;
use Neo\Core\Routing\RouterModule;
use Neo\Core\Routing\RouterViewExtension;
use Neo\Core\Security\SecurityModule;
use Neo\Core\View\ViewModule;
use Neo\Core\Utils\Config\Config;
use PHPUnit\Framework\TestCase;

class RouterModuleTest extends TestCase
{
    public function testDependencies(): void
    {
        $module = new RouterModule();
        self::assertSame([ViewModule::class, SecurityModule::class], $module->dependencies());
    }

    public function testRegisterConfiguresRouterAndTwigBinding(): void
    {
        $container = new Container();

        $container->set('controllersPath', __DIR__);
        $container->set('storagePath', __DIR__);

        $configMock = $this->createMock(Config::class);
        $configMock->method('from')->willReturnSelf();
        $configMock->method('get')->willReturn('dev');
        $container->set(Config::class, $configMock);

        $module = new RouterModule();
        $module->register($container);

        self::assertTrue($container->has(Router::class));
        self::assertTrue($container->has(RouterViewExtension::class));

        $taggedServices = $container->tagged('twig.extension');
        $isFound = false;
        foreach ($taggedServices as $serviceInstance) {
            if ($serviceInstance instanceof RouterViewExtension) {
                $isFound = true;
                break;
            }
        }

        self::assertTrue($isFound, "RouterViewExtension n'est pas enregistré ou taggué correctement.");
    }
}