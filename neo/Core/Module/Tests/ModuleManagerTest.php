<?php
declare(strict_types=1);

namespace Neo\Core\Module\Tests;

use Neo\Core\DI\Container;
use Neo\Core\Module\AbstractModule;
use Neo\Core\Module\Exception\ModuleException;
use Neo\Core\Module\ModuleManager;
use PHPUnit\Framework\TestCase;

final class SimpleModule extends AbstractModule
{
    public static bool $registered = false;
    public static bool $booted = false;

    public function register(Container $container): void
    {
        self::$registered = true;
    }

    public function boot(Container $container): void
    {
        self::$booted = true;
        parent::boot($container);
    }
}

final class DependentModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [SimpleModule::class];
    }
}

final class CircularAModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [CircularBModule::class];
    }
}

final class CircularBModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [CircularAModule::class];
    }
}

final class NoDepsModule extends AbstractModule {}

class OrderRecorderModule extends AbstractModule
{
    public static array $bootOrder = [];

    public function boot(Container $container): void
    {
        self::$bootOrder[] = static::class;
        parent::boot($container);
    }
}

final class FirstModule extends OrderRecorderModule {}

final class SecondModule extends OrderRecorderModule
{
    public function dependencies(): array
    {
        return [FirstModule::class];
    }
}

final class ThirdModule extends OrderRecorderModule
{
    public function dependencies(): array
    {
        return [SecondModule::class];
    }
}

class ModuleManagerTest extends TestCase
{
    private function makeManager(): ModuleManager
    {
        return new ModuleManager(new Container());
    }

    protected function setUp(): void
    {
        SimpleModule::$registered = false;
        SimpleModule::$booted = false;
        OrderRecorderModule::$bootOrder = [];
    }

    public function testBootRegistersModule(): void
    {
        $manager = $this->makeManager();

        $ref = new \ReflectionProperty(ModuleManager::class, 'modules');
        $ref->setValue($manager, [SimpleModule::class]);

        $manager->boot();

        $this->assertTrue(SimpleModule::$registered);
    }

    public function testBootBootsModule(): void
    {
        $manager = $this->makeManager();

        $ref = new \ReflectionProperty(ModuleManager::class, 'modules');
        $ref->setValue($manager, [SimpleModule::class]);

        $manager->boot();

        $this->assertTrue(SimpleModule::$booted);
    }

    public function testBootResolvesModulesInDependencyOrder(): void
    {
        $manager = $this->makeManager();

        $ref = new \ReflectionProperty(ModuleManager::class, 'modules');
        $ref->setValue($manager, [ThirdModule::class, FirstModule::class, SecondModule::class]);

        $manager->boot();

        $this->assertSame([FirstModule::class, SecondModule::class, ThirdModule::class], OrderRecorderModule::$bootOrder);
    }

    public function testBootWithNoDependenciesSucceeds(): void
    {
        $this->expectNotToPerformAssertions();

        $manager = $this->makeManager();

        $ref = new \ReflectionProperty(ModuleManager::class, 'modules');
        $ref->setValue($manager, [NoDepsModule::class]);

        $manager->boot();
    }

    public function testBootWithEmptyModuleListSucceeds(): void
    {
        $this->expectNotToPerformAssertions();

        $manager = $this->makeManager();
        $manager->boot();
    }

    public function testCircularDependencyThrowsModuleException(): void
    {
        $manager = $this->makeManager();

        $ref = new \ReflectionProperty(ModuleManager::class, 'modules');
        $ref->setValue($manager, [CircularAModule::class]);

        $this->expectException(ModuleException::class);
        $manager->boot();
    }

    public function testNonExistentModuleClassThrowsModuleException(): void
    {
        $manager = $this->makeManager();

        $ref = new \ReflectionProperty(ModuleManager::class, 'modules');
        /** @var class-string[] $fakeModules */
        $fakeModules = ['Neo\\Core\\Module\\Tests\\GhostModule_' . uniqid('', false)];
        $ref->setValue($manager, $fakeModules);

        $this->expectException(ModuleException::class);
        $manager->boot();
    }

    public function testDependencyIsBootedBeforeDependent(): void
    {
        $manager = $this->makeManager();

        $ref = new \ReflectionProperty(ModuleManager::class, 'modules');
        $ref->setValue($manager, [SecondModule::class]);

        $manager->boot();

        $firstIdx = array_search(FirstModule::class, OrderRecorderModule::$bootOrder, true);
        $secondIdx = array_search(SecondModule::class, OrderRecorderModule::$bootOrder, true);

        $this->assertLessThan($secondIdx, $firstIdx);
    }

    public function testSameModuleNotBootedTwiceWhenSharedDependency(): void
    {
        $manager = $this->makeManager();

        $ref = new \ReflectionProperty(ModuleManager::class, 'modules');
        $ref->setValue($manager, [SecondModule::class, ThirdModule::class]);

        $manager->boot();

        $count = count(array_filter(OrderRecorderModule::$bootOrder, fn(string $c) => $c === FirstModule::class));
        $this->assertSame(1, $count);
    }

    public function testDiscoverReturnsSelf(): void
    {
        $manager = $this->makeManager();
        $tmpDir = sys_get_temp_dir() . '/neo_test_discover_' . uniqid('', false);
        mkdir($tmpDir, 0777, true);

        try {
            $result = $manager->discover($tmpDir);
            $this->assertSame($manager, $result);
        } finally {
            rmdir($tmpDir);
        }
    }

    public function testDiscoverIgnoresNonModulePhpFiles(): void
    {
        $manager = $this->makeManager();
        $tmpDir = sys_get_temp_dir() . '/neo_test_discover_' . uniqid('', false);
        mkdir($tmpDir, 0777, true);
        file_put_contents($tmpDir . '/SomeHelper.php', '<?php class SomeHelper {}');

        try {
            $manager->discover($tmpDir);

            $ref = new \ReflectionProperty(ModuleManager::class, 'modules');
            $this->assertSame([], $ref->getValue($manager));
        } finally {
            unlink($tmpDir . '/SomeHelper.php');
            rmdir($tmpDir);
        }
    }

    public function testDiscoverLoadsValidModuleFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/neo_test_discover_' . uniqid('', false);
        mkdir($tmpDir, 0777, true);

        $fqcn = 'Neo\\Core\\Module\\Tests\\Discovered\\DiscoveredModule';
        $src = <<<PHP
        <?php
        namespace Neo\\Core\\Module\\Tests\\Discovered;
        use Neo\\Core\\Module\\AbstractModule;
        class DiscoveredModule extends AbstractModule {}
        PHP;

        file_put_contents($tmpDir . '/DiscoveredModule.php', $src);

        try {
            $manager = $this->makeManager();
            $manager->discover($tmpDir);

            $ref = new \ReflectionProperty(ModuleManager::class, 'modules');
            /** @var string[] $modules */
            $modules = $ref->getValue($manager);

            $this->assertContains($fqcn, $modules);
        } finally {
            unlink($tmpDir . '/DiscoveredModule.php');
            rmdir($tmpDir);
        }
    }
}