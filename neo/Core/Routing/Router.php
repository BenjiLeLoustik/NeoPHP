<?php
declare(strict_types=1);

namespace Neo\Core\Routing;

use Neo\Core\DI\Container;
use Neo\Core\Http\Request;
use Neo\Core\Http\Response\Response;
use Neo\Core\Profiler\Profiler;
use Neo\Core\Routing\Attribute\MainRoute as MainRouteAttribute;
use Neo\Core\Routing\Attribute\Route as RouteAttribute;
use Neo\Core\Routing\Exception\RouteNotFoundException;
use Neo\Core\Routing\Exception\RouterException;
use Neo\Core\Security\Middleware\MiddlewareHandler;
use Neo\Core\Tools\Scanner\AttributeScanner;
use Neo\Core\Utils\Config\Config;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class Router
{
    private RouteCollection $routes;
    private Container $container;
    private string $controllersPath;
    private ?string $currentRouteName = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->routes = new RouteCollection();
        $this->controllersPath = $this->container->get('controllersPath');

        $this->scanControllers();
    }

    private function isDebug(): bool
    {
        return $this->container->get(Config::class)->from('app')->get('environment') === 'dev';
    }

    private function scanControllers(): void
    {
        $cacheFile = $this->container->get('storagePath') . '/var/cache/router/routes.php';

        if (!$this->isDebug() && file_exists($cacheFile)) {
            $this->routes = unserialize(file_get_contents($cacheFile));
            return;
        }

        $scannedRoutes = AttributeScanner::scan(RouteAttribute::class)
            ->in($this->controllersPath)
            ->withSuffix('.php')
            ->onMethods()
            ->getResults();

        foreach ($scannedRoutes as $scannedRoute) {
            /** @var \ReflectionClass $refClass */
            $refClass = $scannedRoute['class'];
            /** @var \ReflectionMethod $method */
            $method = $scannedRoute['method'];
            /** @var RouteAttribute $route */
            $route = $scannedRoute['attribute'];

            $fqcn = $refClass->getName();

            $mainRouteAttr = $refClass->getAttributes(MainRouteAttribute::class);
            $prefixPath = '';
            $prefixName = '';
            if (!empty($mainRouteAttr)) {
                $mainRoute = $mainRouteAttr[0]->newInstance();
                $prefixPath = rtrim($mainRoute->path, '/');
                $prefixName = $mainRoute->name . '.';
            }

            $path = $prefixPath . '/' . ltrim($route->path, '/');
            $name = $prefixName . $route->name;

            foreach ($route->methods as $httpMethod) {
                if ($this->isDebug() && $this->routes->has($httpMethod, $path)) {
                    trigger_error(
                        "Route conflict: [{$httpMethod}] {$path} is already defined. "
                        . "Overwritten by {$fqcn}::{$method->getName()}.",
                        E_USER_WARNING
                    );
                }

                $this->routes->add(
                    $httpMethod,
                    $path,
                    $name,
                    $fqcn,
                    $method->getName(),
                    $route->requirements
                );
            }
        }

        if (!$this->isDebug()) {
            $cacheDir = dirname($cacheFile);
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0777, true);
            }
            file_put_contents($cacheFile, serialize($this->routes));
        }
    }

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

    private function match(string $route, string $path, array &$params, array $requirements = []): bool
    {
        $route = '/' . trim($route, '/');

        $pattern = preg_replace_callback(
            '/\/\{([a-zA-Z0-9_]+)(\?)?\}/',
            function ($m) use ($requirements) {
                $paramName = $m[1];
                $isOptional = isset($m[2]);
                $regex = $requirements[$paramName] ?? '[^/]+';

                return $isOptional
                    ? '(?:/(?P<' . $paramName . '>' . $regex . '))?'
                    : '/(?P<' . $paramName . '>' . $regex . ')';
            },
            $route
        );

        if ($pattern === null) return false;

        $pattern = '#^' . rtrim($pattern, '/') . '/?$#';

        if (preg_match($pattern, $path, $matches)) {
            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key) && $value !== '') {
                    $params[$key] = $value;
                }
            }
            return true;
        }

        return false;
    }

    private function invokeHandler(array $routeInfo, array $params): mixed
    {
        try {
            $controller = $this->container->get($routeInfo['controller']);
            $method = $routeInfo['action'];

            if (defined('NEO_PROFILER_ENABLED') && NEO_PROFILER_ENABLED) {
                $rc = Profiler::getInstance()->getCollector('router');
                $rc?->setMatchedRoute($routeInfo['controller'], $method, $params);
            }

            $middlewareHandler = $this->container->get(MiddlewareHandler::class);
            $middlewareHandler->run($routeInfo['controller'], $method);

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

    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    public function getCurrentRouteName(): ?string
    {
        return $this->currentRouteName;
    }
}