<?php
declare(strict_types=1);

namespace Neo\Core\DI;

use Neo\Core\Controller\AbstractController;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Error\Exception\FrameworkException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use ReflectionParameter;

class Container implements ContainerInterface
{
    private array $definitions = [];
    private array $instances = [];
    private array $bindings = [];
    private array $resolving = [];
    private static ?self $instance = null;

    public function __construct()
    {
        self::$instance = $this;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function set(string $id, mixed $value): void
    {
        $this->definitions[$id] = $value;
        unset($this->instances[$id]);
    }

    public function bind(string $abstract, string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        unset($this->instances[$abstract]);
    }

    public function singleton(string $id, callable $factory): void
    {
        $this->set($id, $factory);
    }

    public function instance(string $id, object $object): void
    {
        $this->instances[$id] = $object;
    }

    public function get(string $id): mixed
    {
        if (isset($this->bindings[$id])) {
            $id = $this->bindings[$id];
        }

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->definitions[$id])) {
            $definition = $this->definitions[$id];
            $object = is_callable($definition) ? $definition($this) : $definition;
            return $this->instances[$id] = $object;
        }

        if (class_exists($id)) {
            return $this->instances[$id] = $this->resolveClass($id);
        }

        throw new ContainerException(
            title: "Service Not Found",
            message: sprintf("Service '%s' not found in the container.", $id),
            code: 500,
            context: ['id' => $id]
        );
    }

    public function has(string $id): bool
    {
        return isset($this->definitions[$id])
            || isset($this->instances[$id])
            || isset($this->bindings[$id])
            || class_exists($id);
    }

    public function make(string $id, array $parameters = []): object
    {
        if (isset($this->bindings[$id])) {
            $id = $this->bindings[$id];
        }

        return $this->resolveClass($id, $parameters);
    }

    private function resolveClass(string $class, array $extraParams = []): object
    {
        if (isset($this->resolving[$class])) {
            throw new ContainerException(
                title: "Circular Dependency",
                message: sprintf(
                    "Circular dependency detected while resolving '%s'. Chain: %s -> %s",
                    $class,
                    implode(' → ', array_keys($this->resolving)),
                    $class
                ),
                code: 500,
                context: ['class' => $class, 'chain' => array_keys($this->resolving)]
            );
        }

        $ref = new ReflectionClass($class);

        if (!$ref->isInstantiable()) {
            throw new ContainerException(
                title: "Class Not Instantiable",
                message: sprintf("Class '%s' cannot be instantiated.", $class),
                code: 500,
                context: ['class' => $class]
            );
        }

        $this->resolving[$class] = true;

        try {
            $constructor = $ref->getConstructor();

            if ($this->isAbstractController($ref) && $constructor !== null) {

                $instance = $ref->newInstanceWithoutConstructor();

                $parentConstructor = new \ReflectionMethod(
                    \Neo\Core\Controller\AbstractController::class,
                    '__construct'
                );
                $parentConstructor->invoke($instance, $this);

                foreach ($constructor->getParameters() as $param) {
                    $type = $param->getType();

                    if ($type instanceof ReflectionNamedType
                        && $type->getName() === self::class
                    ) {
                        continue;
                    }

                    $name  = $param->getName();
                    $value = array_key_exists($name, $extraParams)
                        ? $extraParams[$name]
                        : $this->resolveParameter($param);

                    try {
                        $prop = $ref->getProperty($name);
                        $prop->setAccessible(true);
                        $prop->setValue($instance, $value);
                    } catch (\ReflectionException) {}
                }

                return $instance;
            }

            if ($constructor === null) {
                return new $class();
            }

            $params = $this->resolveParameters($constructor, $extraParams);
            return $ref->newInstanceArgs($params);

        } finally {
            unset($this->resolving[$class]);
        }
    }

    private function isAbstractController(ReflectionClass $ref): bool
    {
        $parent = $ref->getParentClass();
        while ($parent) {
            if ($parent->getName() === AbstractController::class) {
                return true;
            }
            $parent = $parent->getParentClass();
        }
        return false;
    }

    private function resolveChildControllerParams(
        \ReflectionMethod $constructor,
        array $extraParams = []
    ): array {
        $resolved = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            $type = $param->getType();
            if ($type instanceof ReflectionNamedType
                && $type->getName() === Container::class
            ) {
                continue;
            }

            if (array_key_exists($name, $extraParams)) {
                $resolved[] = $extraParams[$name];
                continue;
            }

            $resolved[] = $this->resolveParameter($param);
        }

        return $resolved;
    }

    private function resolveParameters(ReflectionFunctionAbstract $method, array $extraParams = []): array
    {
        $resolved = [];

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $extraParams)) {
                $resolved[] = $extraParams[$name];
                continue;
            }

            $resolved[] = $this->resolveParameter($param);
        }

        return $resolved;
    }

    private function resolveParameter(ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $className = $type->getName();

            if ($type->allowsNull() && !$this->has($className)) {
                return null;
            }

            return $this->get($className);
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        $declaringClass = $param->getDeclaringClass()?->getName() ?? 'closure/function';

        throw new ContainerException(
            title: "Parameter Cannot Be Resolved",
            message: sprintf(
                "Cannot resolve parameter '$%s' in '%s'.",
                $param->getName(),
                $declaringClass
            ),
            code: 500,
            context: [
                'parameter' => $param->getName(),
                'class' => $declaringClass,
            ]
        );
    }

    public function call(callable $callable, array $extraParams = []): mixed
    {
        if (is_array($callable)) {
            $ref = new \ReflectionMethod($callable[0], $callable[1]);
        } elseif (is_string($callable) && str_contains($callable, '::')) {
            [$class, $method] = explode('::', $callable);
            $ref = new \ReflectionMethod($class, $method);
        } else {
            $ref = new \ReflectionFunction(\Closure::fromCallable($callable));
        }

        $params = $this->resolveParameters($ref, $extraParams);
        return call_user_func_array($callable, $params);
    }

    public function getDefinitions(): array
    {
        return array_keys($this->definitions);
    }

    public function getInstances(): array
    {
        return array_keys($this->instances);
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }
}