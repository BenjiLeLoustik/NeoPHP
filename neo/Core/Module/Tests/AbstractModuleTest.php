<?php
declare(strict_types=1);

namespace Neo\Core\Module\Tests;

use Neo\Core\DI\Container;
use Neo\Core\Module\AbstractModule;
use PHPUnit\Framework\TestCase;

final class ConcreteModule extends AbstractModule
{
    public function getFromContainer(string $abstract): mixed
    {
        return $this->get($abstract);
    }
}

final class RegisteringModule extends AbstractModule
{
    public bool $registerCalled = false;

    public function register(Container $container): void
    {
        $this->registerCalled = true;
        $container->set('registered_by_module', true);
    }
}

final class ResolvingModule extends AbstractModule
{
    public bool $resolveCalled = false;

    protected function resolveDependencies(): void
    {
        $this->resolveCalled = true;
    }
}

class AbstractModuleTest extends TestCase
{
    public function testDependenciesReturnsEmptyArrayByDefault(): void
    {
        $module = new ConcreteModule();

        $this->assertSame([], $module->dependencies());
    }

    public function testRegisterIsNoopByDefault(): void
    {
        $this->expectNotToPerformAssertions();

        $container = new Container();
        $module = new ConcreteModule();

        $module->register($container);
    }

    public function testBootSetsContainerAndCallsResolveDependencies(): void
    {
        $container = new Container();
        $module = new ResolvingModule();

        $module->boot($container);

        $this->assertTrue($module->resolveCalled);
    }

    public function testGetDelegatesToContainer(): void
    {
        $container = new Container();
        $container->set('my.service', 'hello');

        $module = new ConcreteModule();
        $module->boot($container);

        $this->assertSame('hello', $module->getFromContainer('my.service'));
    }

    public function testRegisterWritesToContainer(): void
    {
        $container = new Container();
        $module = new RegisteringModule();

        $module->register($container);

        $this->assertTrue($module->registerCalled);
        $this->assertTrue($container->get('registered_by_module'));
    }
}