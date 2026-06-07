<?php
declare(strict_types=1);

namespace Neo\Core\DI\Tests;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    private function makeContainer(): Container
    {
        return new Container();
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        $a = Container::getInstance();
        $b = Container::getInstance();

        $this->assertSame($a, $b);
    }

    public function testConstructorSetsGlobalInstance(): void
    {
        $container = new Container();

        $this->assertSame($container, Container::getInstance());
    }

    public function testSetAndGetScalarValue(): void
    {
        $container = $this->makeContainer();
        $container->set('app.name', 'NeoApp');

        $this->assertSame('NeoApp', $container->get('app.name'));
    }

    public function testSetAndGetWithFactory(): void
    {
        $container = $this->makeContainer();
        $container->set('myService', fn (Container $c): object => new \stdClass());

        $this->assertInstanceOf(\stdClass::class, $container->get('myService'));
    }

    public function testGetReturnsSameInstanceOnSecondCall(): void
    {
        $container = $this->makeContainer();
        $container->set('myService', fn (Container $c): object => new \stdClass());

        $first = $container->get('myService');
        $second = $container->get('myService');

        $this->assertSame($first, $second);
    }

    public function testSetOverwritesClearsResolvedInstance(): void
    {
        $container = $this->makeContainer();
        $container->set('val', fn (): string => 'v1');
        $container->get('val');

        $container->set('val', fn (): string => 'v2');

        $this->assertSame('v2', $container->get('val'));
    }

    public function testGetThrowsWhenServiceNotFound(): void
    {
        $container = $this->makeContainer();

        $this->expectException(ContainerException::class);
        $container->get('nonexistent.service');
    }

    public function testGetThrowsContainerExceptionWithCode500(): void
    {
        $container = $this->makeContainer();

        try {
            $container->get('missing');
            $this->fail('Expected ContainerException.');
        } catch (ContainerException $e) {
            $this->assertSame(500, $e->getCode());
        }
    }

    public function testInstanceRegistersPrebuiltObject(): void
    {
        $container = $this->makeContainer();
        $obj = new \stdClass();

        $container->instance('shared', $obj);

        $this->assertSame($obj, $container->get('shared'));
    }

    public function testSingletonBehavesLikeSet(): void
    {
        $container = $this->makeContainer();
        $container->singleton('svc', fn (): object => new \stdClass());

        $first = $container->get('svc');
        $second = $container->get('svc');

        $this->assertSame($first, $second);
    }

    public function testBindResolvesConcreteFromAbstract(): void
    {
        $container = $this->makeContainer();
        $container->bind(\stdClass::class, \stdClass::class);

        $this->assertInstanceOf(\stdClass::class, $container->get(\stdClass::class));
    }

    public function testBindClearsResolvedInstance(): void
    {
        $container = $this->makeContainer();
        $container->set(\stdClass::class, fn (): object => new \stdClass());
        $container->get(\stdClass::class);

        $container->bind(\stdClass::class, \stdClass::class);

        $this->assertNotContains(\stdClass::class, $container->getInstances());
    }

    public function testHasReturnsTrueForRegisteredDefinition(): void
    {
        $container = $this->makeContainer();
        $container->set('key', 'value');

        $this->assertTrue($container->has('key'));
    }

    public function testHasReturnsTrueForRegisteredInstance(): void
    {
        $container = $this->makeContainer();
        $container->instance('obj', new \stdClass());

        $this->assertTrue($container->has('obj'));
    }

    public function testHasReturnsTrueForRegisteredBinding(): void
    {
        $container = $this->makeContainer();
        $container->bind('abstract', \stdClass::class);

        $this->assertTrue($container->has('abstract'));
    }

    public function testHasReturnsTrueForExistingClass(): void
    {
        $container = $this->makeContainer();

        $this->assertTrue($container->has(\stdClass::class));
    }

    public function testHasReturnsFalseForUnknownKey(): void
    {
        $container = $this->makeContainer();

        $this->assertFalse($container->has('totally.unknown'));
    }

    public function testMakeAlwaysReturnsNewInstance(): void
    {
        $container = $this->makeContainer();

        $first = $container->make(\stdClass::class);
        $second = $container->make(\stdClass::class);

        $this->assertNotSame($first, $second);
    }

    public function testMakeResolvesConcreteFromBinding(): void
    {
        $container = $this->makeContainer();
        $container->bind('iface', \stdClass::class);

        $this->assertInstanceOf(\stdClass::class, $container->make('iface'));
    }

    public function testMakeThrowsOnNonExistentClass(): void
    {
        $container = $this->makeContainer();

        $this->expectException(ContainerException::class);
        $container->make('NonExistentClass_' . uniqid('', false));
    }

    public function testGetAutowiresClassWithNoConstructor(): void
    {
        $container = $this->makeContainer();

        $this->assertInstanceOf(\stdClass::class, $container->get(\stdClass::class));
    }

    public function testCallInvokesClosure(): void
    {
        $container = $this->makeContainer();

        $result = $container->call(fn (): string => 'hello');

        $this->assertSame('hello', $result);
    }

    public function testCallInjectsDependenciesIntoClosure(): void
    {
        $dep = new \stdClass();

        $container = $this->makeContainer();
        $container->instance(\stdClass::class, $dep);

        $result = $container->call(fn (\stdClass $s): object => $s);

        $this->assertSame($dep, $result);
    }

    public function testCallInvokesStaticMethodFromString(): void
    {
        $container = $this->makeContainer();

        $result = $container->call(
            'Neo\Core\DI\Tests\ContainerTestHelper::staticMethod'
        );

        $this->assertSame('static', $result);
    }

    public function testCallInvokesMethodFromArray(): void
    {
        $container = $this->makeContainer();
        $obj = new ContainerTestHelper();

        $result = $container->call([$obj, 'instanceMethod']);

        $this->assertSame('instance', $result);
    }

    public function testCallPassesExtraParams(): void
    {
        $container = $this->makeContainer();

        $result = $container->call(
            fn (string $name): string => "Hello $name",
            ['name' => 'World']
        );

        $this->assertSame('Hello World', $result);
    }

    public function testGetDefinitionsContainsRegisteredKey(): void
    {
        $container = $this->makeContainer();
        $container->set('myKey', 'val');

        $this->assertContains('myKey', $container->getDefinitions());
    }

    public function testGetInstancesContainsResolvedKey(): void
    {
        $container = $this->makeContainer();
        $container->set('resolved', fn (): object => new \stdClass());
        $container->get('resolved');

        $this->assertContains('resolved', $container->getInstances());
    }

    public function testGetBindingsContainsRegisteredBinding(): void
    {
        $container = $this->makeContainer();
        $container->bind('abstract', \stdClass::class);

        $this->assertArrayHasKey('abstract', $container->getBindings());
        $this->assertSame(
            \stdClass::class,
            $container->getBindings()['abstract']
        );
    }

    public function testTaggedReturnsResolvedServicesForTag(): void
    {
        $container = $this->makeContainer();

        $obj = new \stdClass();
        $container->instance('svc.a', $obj);
        $container->tag('svc.a', 'my.tag');

        $tagged = $container->tagged('my.tag');

        $this->assertCount(1, $tagged);
        $this->assertSame($obj, $tagged[0]);
    }

    public function testTaggedReturnsEmptyArrayForUnknownTag(): void
    {
        $container = $this->makeContainer();

        $this->assertSame([], $container->tagged('unknown.tag'));
    }

    public function testTagSupportsMultipleTagsAtOnce(): void
    {
        $container = $this->makeContainer();

        $container->instance('svc', new \stdClass());
        $container->tag('svc', 'tag.a', 'tag.b');

        $this->assertCount(1, $container->tagged('tag.a'));
        $this->assertCount(1, $container->tagged('tag.b'));
    }

    public function testTaggedCanGroupMultipleServices(): void
    {
        $container = $this->makeContainer();

        $container->instance('svc.1', new \stdClass());
        $container->instance('svc.2', new \stdClass());

        $container->tag('svc.1', 'group');
        $container->tag('svc.2', 'group');

        $this->assertCount(2, $container->tagged('group'));
    }
}

final class ContainerTestHelper
{
    public static function staticMethod(): string
    {
        return 'static';
    }

    public function instanceMethod(): string
    {
        return 'instance';
    }
}