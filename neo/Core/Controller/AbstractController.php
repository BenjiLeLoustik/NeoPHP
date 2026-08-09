<?php
declare(strict_types=1);

namespace Neo\Core\Controller;

use Closure;
use Neo\Core\Controller\Exception\AbstractControllerException;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Extension\ExtensionManager;

abstract class AbstractController
{
    protected Container $container;

    /** @var array<string, Closure> */
    private array $methods = [];

    /** @var array<string, Closure> */
    private array $propertyResolvers = [];

    /** @var array<string, mixed> */
    private array $propertyCache = [];

    /**
     * @throws ContainerException
     */
    public function __construct(?Container $container = null)
    {
        if ($container === null) return;

        $this->container = $container;

        $container->get(ExtensionManager::class)->applyToController($this);
    }

    public function registerMethod(string $name, Closure $resolver): void
    {
        $this->methods[$name] = $resolver;
    }

    public function registerProperty(string $name, Closure $resolver): void
    {
        $this->propertyResolvers[$name] = $resolver;
    }

    /**
     * @param list<mixed> $arguments
     * @throws AbstractControllerException
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (isset($this->methods[$name])) {
            return ($this->methods[$name])(...$arguments);
        }

        throw new AbstractControllerException(
            title: 'Controller Error',
            message: sprintf("Method '%s' is not registered on this controller.", $name),
            code: 500
        );
    }

    /**
     * @throws AbstractControllerException
     */
    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->propertyCache)) {
            return $this->propertyCache[$name];
        }

        if (isset($this->propertyResolvers[$name])) {
            return $this->propertyCache[$name] = ($this->propertyResolvers[$name])();
        }

        throw new AbstractControllerException(
            title: 'Controller Error',
            message: sprintf(
                "Property '%s' is not registered on this controller.",
                $name
            ),
            code: 500
        );
    }
}