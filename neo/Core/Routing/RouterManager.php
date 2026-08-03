<?php
declare(strict_types=1);

namespace Neo\Core\Routing;

use JsonException;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Request\Request;
use Neo\Core\Http\Response\Types\Response;
use Neo\Core\Profiler\ProfilerManager;
use Neo\Core\Routing\Attribute\MainRoute as MainRouteAttribute;
use Neo\Core\Routing\Attribute\Route as RouteAttribute;
use Neo\Core\Routing\Collection\RouteCollection;
use Neo\Core\Routing\Exception\RouteNotFoundException;
use Neo\Core\Routing\Exception\RouterException;
use Neo\Core\Security\Middleware\MiddlewareManager;
use Neo\Core\Utils\Scanner\ScannerAttributeManager;
use Neo\Core\Utils\Scanner\ScannerFileManager;
use ReflectionException;
use ReflectionMethod;
use Throwable;

class RouterManager
{
    private RouteCollection $routes;

    private Container $container;

    private string $controllersPath;

    private ?string $currentRouteName = null;

    /** @var array<string, string> */
    private array $compiledPatterns = [];

    /**
     * @throws ContainerException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->routes = new RouteCollection();
        $this->controllersPath = $this->container->get('controllersPath');

        $this->scanControllers();
    }

    /**
     * @throws ContainerException
     */
    private function isDebug(): bool
    {
        return $this->container->get('router.configModule')->from('app')->get('environment') === 'dev';
    }

    /**
     * @throws ContainerException
     * @throws JsonException
     * @throws ReflectionException
     */
    private function scanControllers(): void
    {
        $cacheFile = $this->container->get('storagePath') . '/var/cache/router/routes.json';

        if (!$this->isDebug() && file_exists($cacheFile)) {
            $json = file_get_contents($cacheFile);
            if ($json !== false) {
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                $this->routes = RouteCollection::fromArray($data);
                return;
            }
        }

        $results = new ScannerFileManager()
            ->paths([$this->controllersPath])
            ->scan();

        foreach ($results as $result) {
            $this->processControllerClass($result->getFqcn());
        }

        if (!$this->isDebug()) {
            $cacheDir = dirname($cacheFile);
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            file_put_contents(
                $cacheFile,
                json_encode($this->routes->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
            );
        }
    }

    private function processControllerClass(string $fqcn): void
    {
        if (!class_exists($fqcn)) {
            return;
        }

        $results = new ScannerAttributeManager($fqcn)
            ->onClass()
            ->onMethods(ReflectionMethod::IS_PUBLIC)
            ->scan();

        $prefixPath = '';
        $prefixName = '';
        foreach ($results as $entry) {
            if ($entry->getType() === 'class' && $entry->getAttribute() instanceof MainRouteAttribute) {
                $prefixPath = rtrim($entry->getAttribute()->path, '/');
                $prefixName = $entry->getAttribute()->name . '.';
                break;
            }
        }

        foreach ($results as $entry) {
            if ($entry->getType() !== 'method' || !($entry->getAttribute() instanceof RouteAttribute)) {
                continue;
            }

            $route = $entry->getAttribute();
            $refMethod = $entry->getReflection();

            if (!$refMethod instanceof ReflectionMethod) {
                continue;
            }

            $action = $refMethod->getName();
            $path = $prefixPath . '/' . ltrim($route->path, '/');
            $name = $prefixName . $route->name;

            foreach ($route->methods as $httpMethod) {
                if ($this->isDebug() && $this->routes->has($httpMethod, $path)) {
                    trigger_error(
                        "Route conflict: [{$httpMethod}] {$path} is already defined. "
                        . "Overwritten by {$fqcn}::{$action}.",
                        E_USER_WARNING
                    );
                }

                $this->routes->add($httpMethod, $path, $name, $fqcn, $action, $route->requirements);
            }
        }
    }

    /**
     * @throws RouterException
     * @throws RouteNotFoundException
     */
    public function dispatch(Request $request, Response $response): Response
    {
        $method = strtoupper($request->getMethod());
        $path = '/' . trim($request->getPath(), '/');
        $routes = $this->routes->all();

        $pathExists = false;

        foreach ($routes as $httpMethod => $methodRoutes) {
            foreach ($methodRoutes as $routePath => $info) {
                $params = [];
                if ($this->match($routePath, $path, $params, $info['requirements'] ?? [])) {
                    if ($httpMethod === $method) {
                        $this->currentRouteName = $info['name'];
                        return $this->invokeHandler($info, $params);
                    }
                    $pathExists = true;
                }
            }
        }

        if ($pathExists) {
            throw new RouterException(
                title: "Method Not Allowed",
                message: sprintf("HTTP method '%s' is not allowed for '%s'.", $method, $path),
                code: 405,
                context: ['method' => $method, 'path' => $path]
            );

        }

        throw new RouteNotFoundException(
            title: "Not Found",
            message: sprintf("No route found for '%s'.", $path),
            code: 404,
            context: ['method' => $method, 'path' => $path]
        );
    }

    /**
     * @param array<string, string> $params
     * @param array<string, string> $requirements
     */
    private function match(string $route, string $path, array &$params, array $requirements = []): bool
    {
        $pattern = $this->compilePattern($route, $requirements);

        if (preg_match($pattern, $path, $matches) !== 1) {
            return false;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (!is_int($key) && $value !== '') {
                $params[$key] = $value;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $routeInfo
     * @param array<string, string> $params
     * @throws RouterException
     */
    private function invokeHandler(array $routeInfo, array $params): mixed
    {
        try {
            $controller = $this->container->get($routeInfo['controller']);
            $method = $routeInfo['action'];

            if (defined('NEO_PROFILER_ENABLED') && NEO_PROFILER_ENABLED) {
                $rc = ProfilerManager::getInstance()->getCollector('router');
                $rc?->setMatchedRoute($routeInfo['controller'], $method, $params);
            }

            $middlewareHandler = $this->container->get(MiddlewareManager::class);
            $middlewareResponse = $middlewareHandler->run($routeInfo['controller'], $method);

            if ($middlewareResponse !== null) {
                $middlewareResponse->send();
                return $middlewareResponse;
            }

            $refMethod = new \ReflectionMethod($controller, $method);
            $resolved = [];

            foreach ($refMethod->getParameters() as $param) {
                $name = $param->getName();

                if (isset($params[$name])) {
                    $resolved[] = $params[$name];
                    continue;
                }

                $type = $param->getType();
                if ($type && !$type->isBuiltin()) {
                    $resolved[] = $this->container->get($type->getName());
                    continue;
                }

                if ($param->isDefaultValueAvailable()) {
                    $resolved[] = $param->getDefaultValue();
                    continue;
                }

                throw new RouterException(
                    title: "Injection Error",
                    message: sprintf("Cannot inject parameter '$%s' into %s::%s.", $name, $routeInfo['controller'], $method),
                    code: 500,
                    context: ['controller' => $routeInfo['controller'], 'method' => $method]
                );
            }

            return $refMethod->invokeArgs($controller, $resolved);

        } catch (Throwable $e) {
            if ($e instanceof RouterException || $e instanceof RouteNotFoundException) throw $e;

            throw new RouterException(
                title: "Controller Invocation Error",
                message: $e->getMessage(),
                code: 500,
                context: ['routeInfo' => $routeInfo, 'params' => $params],
                previous: $e
            );
        }
    }

    /**
     * @param array<string, string> $params
     * @throws RouteNotFoundException
     */
    public function generateUrl(string $name, array $params = []): string
    {
        foreach ($this->routes->all() as $methodRoutes) {
            foreach ($methodRoutes as $routePath => $info) {
                if ($info['name'] === $name) {
                    $url = $routePath;

                    foreach ($params as $key => $value) {
                        $url = str_replace('{' . $key . '}', (string)$value, $url);
                        $url = str_replace('{' . $key . '?}', (string)$value, $url);
                    }

                    $url = preg_replace('/\/\{[a-zA-Z0-9_]+\?\}/', '', $url);
                    $url = preg_replace('/\{[a-zA-Z0-9_]+\}/', '', $url);
                    $url = '/' . trim(preg_replace('#/+#', '/', $url), '/');

                    return $url;
                }
            }
        }

        throw new RouteNotFoundException(
            title: "Route Not Found",
            message: sprintf("Route '%s' not found.", $name),
            code: 404,
            context: []
        );
    }

    /**
     * @return array{controller: class-string, action: string}|null
     */
    public function findRouteInfo(string $name): ?array
    {
        foreach ($this->routes->all() as $methodRoutes) {
            foreach ($methodRoutes as $info) {
                if ($info['name'] === $name) {
                    return [
                        'controller' => $info['controller'],
                        'action' => $info['action'],
                    ];
                }
            }
        }

        return null;
    }

    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    public function getCurrentRouteName(): ?string
    {
        return $this->currentRouteName;
    }

    /**
     * @param array<string, string> $requirements
     */
    private function compilePattern(string $route, array $requirements): string
    {
        $route = '/' . trim($route, '/');

        if (isset($this->compiledPatterns[$route])) {
            return $this->compiledPatterns[$route];
        }

        $route = '/' . trim($route, '/');

        $pattern = preg_replace_callback(
            '/\{([a-zA-Z0-9_]+)(\?)?\}|([^{]+)/',
            function ($m) use ($requirements) {
                if (!isset($m[1]) || $m[1] === '') {
                    return preg_quote($m[3], '#');
                }

                $paramName  = $m[1];
                $isOptional = isset($m[2]) && $m[2] === '?';
                $regex      = $requirements[$paramName] ?? '[^/]+';

                set_error_handler(static fn() => true);
                $isValid = @preg_match('#' . $regex . '#', '') !== false;
                restore_error_handler();
                if (!$isValid) {
                    $regex = '[^/]+';
                }

                return $isOptional
                    ? '(?P<' . $paramName . '>' . $regex . ')?'
                    : '(?P<' . $paramName . '>' . $regex . ')';
            },
            $route
        );

        $pattern = '#^' . rtrim($pattern ?? '', '/') . '/?$#';
        $this->compiledPatterns[$route] = $pattern;

        return $pattern;
    }
}