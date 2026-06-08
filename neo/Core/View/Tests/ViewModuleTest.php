<?php
declare(strict_types=1);

namespace Neo\Core\View\Tests;

use Neo\Core\DI\Container;
use Neo\Core\Utils\Config\Config;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\View\View;
use Neo\Core\View\ViewModule;
use PHPUnit\Framework\TestCase;

class ViewModuleTest extends TestCase
{
    public function testDependenciesExposesConfigModule(): void
    {
        $module = new ViewModule();
        self::assertSame([ConfigModule::class], $module->dependencies());
    }

    public function testRegisterConfiguresViewService(): void
    {
        $container = new Container();

        $configMock = $this->createMock(Config::class);
        $configMock->method('from')->with('twig')->willReturnSelf();
        $configMock->method('all')->willReturn(['cache' => false]);
        $container->set(Config::class, $configMock);
        $container->set('viewsPath', __DIR__);

        $module = new ViewModule();
        $module->register($container);

        self::assertTrue($container->has(View::class));
    }
}