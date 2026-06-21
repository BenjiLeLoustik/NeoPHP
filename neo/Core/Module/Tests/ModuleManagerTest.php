<?php

namespace Neo\Core\Module\Tests;

use Neo\Core\DI\Container;
use Neo\Core\Module\Exception\ModuleException;
use Neo\Core\Module\ModuleManager;
use Neo\Core\Module\Tests\Fixture\ModuleCallLog;
use Neo\Core\Module\Tests\Fixture\Modules\Dependency\AModule;
use Neo\Core\Module\Tests\Fixture\Modules\Dependency\BModule;
use Neo\Core\Module\Tests\Fixture\Modules\Invalid\AbstractSkippedModule;
use Neo\Core\Module\Tests\Fixture\Modules\Invalid\UnrelatedModule;
use Neo\Core\Module\Tests\Fixture\Modules\Simple\SimpleModule;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ModuleManagerTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/Fixture/Modules';
        ModuleCallLog::reset();
    }

    /**
     * @return array<int, class-string>
     */
    private function getDiscoveredModules(ModuleManager $manager): array
    {
        $ref = new \ReflectionClass($manager);
        $prop = $ref->getProperty('modules');

        /** @var array<int, class-string> $value */
        $value = $prop->getValue($manager);

        return $value;
    }

    public function testDiscoverIgnoresAbstractAndUnrelatedClasses(): void
    {
        $manager = new ModuleManager(new Container());
        $manager->discover($this->fixturesDir . '/Invalid');

        $modules = $this->getDiscoveredModules($manager);

        self::assertNotContains(AbstractSkippedModule::class, $modules);
        self::assertNotContains(UnrelatedModule::class, $modules);
    }

    /**
     * @throws ModuleException
     */
    public function testBootRegistersThenBootsModule(): void
    {
        $manager = new ModuleManager(new Container());
        $manager->discover($this->fixturesDir . '/Simple');
        $manager->boot();

        self::assertSame(
            [SimpleModule::class . '::register', SimpleModule::class . '::boot'],
            ModuleCallLog::$calls
        );
    }

    /**
     * @throws ModuleException
     */
    public function testBootResolvesDependencyOrder(): void
    {
        $manager = new ModuleManager(new Container());
        $manager->discover($this->fixturesDir . '/Dependency');
        $manager->boot();

        self::assertSame(
            [
                BModule::class . '::register',
                AModule::class . '::register',
                BModule::class . '::boot',
                AModule::class . '::boot',
            ],
            ModuleCallLog::$calls
        );
    }

    public function testBootThrowsOnCircularDependency(): void
    {
        $manager = new ModuleManager(new Container());
        $manager->discover($this->fixturesDir . '/Circular');

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $manager->boot();
    }

    public function testBootThrowsWhenDependencyClassMissing(): void
    {
        $manager = new ModuleManager(new Container());
        $manager->discover($this->fixturesDir . '/MissingDependency');

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('does not exist');

        $manager->boot();
    }

    public function testDiscoverReturnsSelfForChaining(): void
    {
        $manager = new ModuleManager(new Container());

        self::assertSame($manager, $manager->discover($this->fixturesDir . '/Simple'));
    }
}