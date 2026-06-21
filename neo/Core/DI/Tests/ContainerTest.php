<?php
declare(strict_types=1);

namespace Neo\Core\DI\Tests;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\DI\Tests\Fixture\StubConcrete;
use Neo\Core\DI\Tests\Fixture\StubInterface;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    /**
     * @throws ContainerException
     */
    public function testGetReturnsValueSetDirectly(): void
    {
        $this->container->set('foo', 'bar');

        self::assertSame('bar', $this->container->get('foo'));
    }

    /**
     * @throws ContainerException
     */
    public function testGetResolvesFactory(): void
    {
        $this->container->set('foo', fn() => new \stdClass());

        self::assertInstanceOf(\stdClass::class, $this->container->get('foo'));
    }

    /**
     * @throws ContainerException
     */
    public function testGetReturnsSameInstanceOnSubsequentCalls(): void
    {
        $this->container->set('foo', fn() => new \stdClass());

        self::assertSame($this->container->get('foo'), $this->container->get('foo'));
    }

    public function testGetThrowsForUnknownId(): void
    {
        try {
            $this->container->get('unknown');
            self::fail('Expected ContainerException.');
        } catch (ContainerException $e) {
            self::assertStringContainsString('unknown', $e->getMessage());
        }
    }

    public function testHasReturnsTrueForRegisteredDefinition(): void
    {
        $this->container->set('foo', 'bar');

        self::assertTrue($this->container->has('foo'));
    }

    public function testHasReturnsFalseForUnknownId(): void
    {
        self::assertFalse($this->container->has('__unknown__'));
    }

    /**
     * @throws ContainerException
     */
    public function testBindResolvesConcreteWhenAbstractIsRequested(): void
    {
        $this->container->bind(StubInterface::class, StubConcrete::class);

        self::assertInstanceOf(StubConcrete::class, $this->container->get(StubInterface::class));
    }

    /**
     * @throws ContainerException
     */
    public function testInstanceReturnsSameObject(): void
    {
        $obj = new \stdClass();
        $this->container->instance('obj', $obj);

        self::assertSame($obj, $this->container->get('obj'));
    }

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    public function testMakeAlwaysReturnsNewInstance(): void
    {
        $a = $this->container->make(\stdClass::class);
        $b = $this->container->make(\stdClass::class);

        self::assertNotSame($a, $b);
    }

    public function testTaggedReturnsAllServicesForTag(): void
    {
        $this->container->set('a', fn() => 'A');
        $this->container->set('b', fn() => 'B');
        $this->container->tag('a', 'letters');
        $this->container->tag('b', 'letters');

        self::assertSame(['A', 'B'], $this->container->tagged('letters'));
    }
}