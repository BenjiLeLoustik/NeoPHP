<?php
declare(strict_types=1);

namespace Neo\Core\Testing;

use Neo\App;
use Neo\Core\DI\Container;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class MiddlewareTestCase extends PHPUnitTestCase
{
    protected Container $container;
    protected static ?App $app = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (static::$app === null) {
            static::$app = new App();
        }

        $this->container = static::$app->getContainer();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    protected function get(string $id): mixed
    {
        return $this->container->get($id);
    }

    protected function swap(string $id, mixed $value): void
    {
        $this->container->set($id, fn() => $value);
    }

    protected function makeMiddleware(string $middlewareClass, array $params = []): MiddlewareInterface
    {
        $instance = empty($params)
            ? $this->container->get($middlewareClass)
            : $this->container->make($middlewareClass, $params);

        $this->assertInstanceOf(
            MiddlewareInterface::class,
            $instance,
            "$middlewareClass must implement MiddlewareInterface."
        );

        return $instance;
    }

    protected function assertMiddlewarePasses(MiddlewareInterface $middleware): void
    {
        $result = $middleware->handle();
        $this->assertTrue($result, 'The middleware should let the request pass (return true).');
    }

    protected function assertMiddlewareBlocks(MiddlewareInterface $middleware): void
    {
        try {
            $result = $middleware->handle();
            $this->assertFalse($result, 'The middleware should block the request (return false).');
        } catch (FrameworkException $e) {
            $this->assertTrue(true);
        }
    }

    protected function assertMiddlewareBlocksWithCode(MiddlewareInterface $middleware, int $expectedCode): void
    {
        try {
            $middleware->handle();
            $this->fail('The middleware should have thrown a FrameworkException.');
        } catch (FrameworkException $e) {
            $this->assertSame(
                $expectedCode,
                $e->getCode(),
                "Expected HTTP code: $expectedCode, got: {$e->getCode()}"
            );
        }
    }
}