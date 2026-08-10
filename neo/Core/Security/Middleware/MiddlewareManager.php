<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Http\Response\Types\Response;
use Neo\Core\Profiler\TimelineRecorder;
use Neo\Core\Routing\Attribute\Maintenance;
use Neo\Core\Routing\Attribute\RateLimit;
use Neo\Core\Routing\Exception\RouteNotFoundException;
use Neo\Core\Security\Middleware\Attribute\IsGranted;
use Neo\Core\Security\Middleware\Attribute\Middleware as MiddlewareAttribute;
use Neo\Core\Security\Middleware\Default\IsGrantedMiddleware;
use Neo\Core\Security\Middleware\Default\RateLimitMiddleware;
use Neo\Core\Security\Middleware\Exception\MiddlewareException;
use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;
use Neo\Core\Security\Middleware\Meta\MiddlewareMeta;
use Neo\Core\Utils\Scanner\ScannerAttributeManager;
use ReflectionException;
use ReflectionMethod;
use Throwable;

class MiddlewareManager
{
    private Container $container;

    /** @var array<string, array<string, array<string, array<int, string>>>> */
    private array $errors = [];

    /** @var array<string, array<int, bool>> */
    private array $executed = [];

    private ?string $lastController = null;

    private ?string $lastMethod = null;

    /** @var array<string, list<MiddlewareMeta>> */
    private array $middlewareCache = [];

    /**
     * @var list<array{
     *     class: class-string,
     *     scope: 'class'|'method',
     *     params: array<string, mixed>,
     *     priority: int,
     *     onError: string,
     *     redirect: string|null,
     *     passed: bool,
     *     message: string,
     *     errorClass: string|null,
     *     duration: float
     * }>
     */
    private array $log = [];

    private bool $maintenanceTriggered = false;

    public function __construct(
        Container $container
    ) {
        $this->container = $container;
    }

    /**
     * @throws MiddlewareException
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function run(string $controller, ?string $method = null): ?Response
    {
        $this->lastController = $controller;
        $this->lastMethod = $method;

        $maintenanceResponse = $this->checkMaintenance($controller, $method);
        if ($maintenanceResponse !== null) {
            $this->maintenanceTriggered = true;
            return $maintenanceResponse;
        }

        $middlewares = $this->getMiddlewares($controller, $method);

        $router = $this->container->get('middleware.routerModule');
        $response = $this->container->get(Response::class);
        $flash = $this->container->get('middleware.clientModule')->flash();

        foreach ($middlewares as $meta) {
            $middlewareClass = $meta->getClass();
            $onError = $meta->getOnError();
            $message = $meta->getMessage();
            $redirect = $meta->getRedirect();
            $isClassMiddleware = $meta->isClass();
            $result = false;
            $start = microtime(true);

            if (!class_exists($middlewareClass)) {
                $this->recordError($middlewareClass, $message, $onError);
                $this->recordLog($middlewareClass, $meta, $start, false, $message, 'class_not_found');
                return $this->handleFailure($message, $onError, $redirect, $response, $flash, $router, $isClassMiddleware);
            }

            $middleware = empty($meta->getParams())
                ? $this->container->get($middlewareClass)
                : $this->container->make($middlewareClass, $meta->getParams());

            if (!$middleware instanceof MiddlewareInterface) {
                $this->recordError($middlewareClass, 'Middleware must implement MiddlewareInterface', $onError);
                $this->recordLog($middlewareClass, $meta, $start, false, 'Middleware must implement MiddlewareInterface', 'invalid_interface');
                return $this->handleFailure($message, $onError, $redirect, $response, $flash, $router, $isClassMiddleware);
            }

            $errorDuringHandle = null;

            try {
                $result = $middleware->handle() === true;
            } catch (Throwable $e) {
                $message = $e->getMessage();
                $errorDuringHandle = $e::class;
                $result = false;
            }

            $this->executed[$middlewareClass][] = $result;
            $this->recordLog($middlewareClass, $meta, $start, $result, $message, $errorDuringHandle);

            if (!$result) {
                $this->recordError($middlewareClass, $message, $onError);
                return $this->handleFailure($message, $onError, $redirect, $response, $flash, $router, $isClassMiddleware);
            }
        }

        return null;
    }

    private function recordLog(
        string $middlewareClass,
        MiddlewareMeta $meta,
        float $start,
        bool $result,
        string $message,
        ?string $errorClass,
    ): void
    {
        $this->log[] = [
            'class' => $middlewareClass,
            'scope' => $meta->isClass() ? 'class' : 'method',
            'params' => $meta->getParams(),
            'priority' => $meta->getPriority(),
            'onError' => $meta->getOnError(),
            'redirect' => $meta->getRedirect(),
            'passed' => $result,
            'message' => $message,
            'errorClass' => $errorClass,
            'duration' => round((microtime(true) - $start) * 1000, 2),
        ];

        if (class_exists(TimelineRecorder::class)) {
            TimelineRecorder::record('middleware', $middlewareClass, $start);
        }
    }

    /**
     * @return list<array{
     *     class: class-string,
     *     scope: 'class'|'method',
     *     params: array<string, mixed>,
     *     priority: int,
     *     onError: string,
     *     redirect: string|null,
     *     passed: bool,
     *     message: string,
     *     errorClass: string|null,
     *     duration: float
     * }>
     */
    public function getExecutionLog(): array
    {
        return $this->log;
    }

    public function wasMaintenanceTriggered(): bool
    {
        return $this->maintenanceTriggered;
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function isAccessible(string $controller, ?string $method = null): bool
    {
        foreach ($this->getMiddlewares($controller, $method) as $meta) {
            $middlewareClass = $meta->getClass();

            if (!class_exists($middlewareClass)) {
                return false;
            }

            $middleware = empty($meta->getParams())
                ? $this->container->get($middlewareClass)
                : $this->container->make($middlewareClass, $meta->getParams());

            if (!$middleware instanceof MiddlewareInterface) {
                return false;
            }

            try {
                if ($middleware->handle() !== true) {
                    return false;
                }
            } catch (Throwable) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws MiddlewareException
     * @throws FrameworkException
     * @throws RouteNotFoundException
     */
    private function handleFailure(
        string $message,
        string $onError,
        ?string $redirect,
        Response $response,
        mixed $flash,
        mixed $router,
        bool $isClassMiddleware
    ): ?Response
    {
        if ($redirect !== null) {
            if ($message !== '') {
                $flash->add('warning', $message);
            }
            $url = $router->generateUrl($redirect);
            return $response->setHeader('Location', $url)->setStatusCode(302);
        }

        if ($onError === 'block') {
            throw new MiddlewareException(
                title: 'Middleware Error',
                message: $message,
                code: 403
            );
        }

        if ($onError === 'soft' && $message !== '') {
            $flash->add('warning', $message);
        }

        return null;
    }

    private function recordError(string $middleware, string $message, string $onError): void
    {
        $controller = $this->lastController ?? 'unknown';
        $method = $this->lastMethod ?? 'unknown';
        $this->errors[$controller][$method][$middleware][] = $message;
    }

    /**
     * @return list<MiddlewareMeta>
     * @throws ReflectionException
     */
    private function getMiddlewares(string $controller, ?string $method = null): array
    {
        $cacheKey = $controller . '::' . ($method ?? '');

        if (isset($this->middlewareCache[$cacheKey])) {
            return $this->middlewareCache[$cacheKey];
        }

        $all = [];

        $classResults = new ScannerAttributeManager($controller)
            ->onClass()
            ->scan();

        foreach ($classResults as $entry) {
            $attribute = $entry->getAttribute();

            if ($attribute instanceof MiddlewareAttribute) {
                $all[] = new MiddlewareMeta(
                    class: $attribute->use,
                    message: $attribute->message,
                    onError: $attribute->onError,
                    redirect: $attribute->redirect,
                    isClass: true,
                    params: $attribute->params,
                    priority: $attribute->priority,
                );
            } elseif ($attribute instanceof RateLimit) {
                $all[] = $this->buildRateLimitMeta($attribute, true);
            } elseif ($attribute instanceof IsGranted) {
                $all[] = $this->buildIsGrantedMeta($attribute, true);
            }
        }

        if ($method) {
            $methodResults = new ScannerAttributeManager($controller)
                ->onMethods(ReflectionMethod::IS_PUBLIC)
                ->scan();

            foreach ($methodResults as $entry) {
                $refMethod = $entry->getReflection();

                if (!$refMethod instanceof ReflectionMethod || $refMethod->getName() !== $method) {
                    continue;
                }

                $attribute = $entry->getAttribute();

                if ($attribute instanceof MiddlewareAttribute) {
                    $all[] = new MiddlewareMeta(
                        class: $attribute->use,
                        message: $attribute->message,
                        onError: $attribute->onError,
                        redirect: $attribute->redirect,
                        isClass: false,
                        params: $attribute->params,
                        priority: $attribute->priority,
                    );
                } elseif ($attribute instanceof RateLimit) {
                    $all[] = $this->buildRateLimitMeta($attribute, false);
                } elseif ($attribute instanceof IsGranted) {
                    $all[] = $this->buildIsGrantedMeta($attribute, false);
                }
            }
        }

        usort($all, static fn(MiddlewareMeta $a, MiddlewareMeta $b): int => $b->getPriority() <=> $a->getPriority());

        return $this->middlewareCache[$cacheKey] = $all;
    }

    private function buildRateLimitMeta(RateLimit $attr, bool $isClass): MiddlewareMeta
    {
        return new MiddlewareMeta(
            class: RateLimitMiddleware::class,
            message: $attr->message,
            onError: 'block',
            redirect: null,
            isClass: $isClass,
            params: [
                'maxAttempts' => $attr->maxAttempts,
                'decaySeconds' => $attr->decaySeconds,
                'message' => $attr->message,
            ],
            priority: 0,
        );
    }

    private function buildIsGrantedMeta(IsGranted $attr, bool $isClass): MiddlewareMeta
    {
        return new MiddlewareMeta(
            class: IsGrantedMiddleware::class,
            message: $attr->message !== '' ? $attr->message : 'Access denied.',
            onError: $attr->onError,
            redirect: $attr->redirect,
            isClass: $isClass,
            params: [
                'roles' => $attr->roles,
            ],
            priority: 0,
        );
    }

    /**
     * @return list<string>
     */
    public function getErrors(?string $middlewareClass = null): array
    {
        if ($this->lastController === null) return [];

        $allErrors = $this->errors[$this->lastController][$this->lastMethod] ?? [];

        if ($middlewareClass === null) {
            $merged = [];
            foreach ($allErrors as $errorsPerMiddleware) {
                $merged = array_merge($merged, $errorsPerMiddleware);
            }
            return $merged;
        }

        return $allErrors[$middlewareClass] ?? [];
    }

    public function hasError(): bool
    {
        return !empty($this->getErrors());
    }

    /**
     * @return list<bool>
     */
    public function getMiddleware(string $middlewareClass): array
    {
        return $this->executed[$middlewareClass] ?? [];
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
    private function checkMaintenance(string $controller, ?string $method): ?Response
    {
        $maintenance = null;

        if ($method) {
            $methodResults = new ScannerAttributeManager($controller)
                ->onMethods(ReflectionMethod::IS_PUBLIC)
                ->withAttribute(Maintenance::class)
                ->scan();

            foreach ($methodResults as $entry) {
                $refMethod = $entry->getReflection();

                if ($refMethod instanceof ReflectionMethod && $refMethod->getName() === $method) {
                    /** @var Maintenance $attribute */
                    $attribute = $entry->getAttribute();
                    $maintenance = $attribute;
                    break;
                }
            }
        }

        if ($maintenance === null) {
            $classResults = new ScannerAttributeManager($controller)
                ->onClass()
                ->withAttribute(Maintenance::class)
                ->scan();

            if (!empty($classResults)) {
                /** @var Maintenance $attribute */
                $attribute = array_first($classResults)->getAttribute();
                $maintenance = $attribute;
            }
        }

        if ($maintenance === null) {
            return null;
        }

        $view = $this->container->get('middleware.viewModule');
        $response = $this->container->get(Response::class);

        $rendered = $view->renderIfExists('maintenance.html.twig', [
            'message' => $maintenance->message,
        ]);

        $response->setStatusCode(503);

        return $rendered !== null
            ? $response->setContent($rendered)
            : $response->setContent($maintenance->message);
    }
}