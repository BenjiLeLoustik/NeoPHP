<?php
declare(strict_types=1);

namespace Neo\Core\Testing;

use Neo\App;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
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

    /**
     * @throws ContainerException
     * @throws \ReflectionException
     */
    protected function get(string $id): mixed
    {
        return $this->container->get($id);
    }

    protected function swap(string $id, mixed $value): void
    {
        $this->container->set($id, fn() => $value);
    }
}