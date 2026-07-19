<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Http\Client\Flash\Flash;
use Neo\Core\Http\Response\Response;
use Neo\Core\Routing\Attribute\Maintenance;
use Neo\Core\Routing\Attribute\RateLimit;
use Neo\Core\Routing\Exception\RouteNotFoundException;
use Neo\Core\Routing\RouterManager;
use Neo\Core\Security\Middleware\Attribute\IsGranted;
use Neo\Core\Security\Middleware\Attribute\Middleware as MiddlewareAttribute;
use Neo\Core\Security\Middleware\Default\IsGrantedMiddleware;
use Neo\Core\Security\Middleware\Default\RateLimitMiddleware;
use Neo\Core\Security\Middleware\Exception\MiddlewareException;
use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;
use Neo\Core\Utils\Scanner\Attribute\ScannerAttribute;
use Neo\Core\View\ViewManager;
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

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $middlewareCache = [];

    public function __construct(Container $container)
    {
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
            return $maintenanceResponse;
        }

        $middlewares = $this->getMiddlewares($controller, $method);

        $router = $this->container->get(RouterManager::class);
        $response = $this->container->get(Response::class);
        $flash = $this->container->get(Flash::class);

        foreach ($middlewares as $meta) {
            $middlewareClass = $meta['class'];
            $onError = $meta['onError'] ?? 'block';
            $message = $meta['message'] ?? 'Middleware failed';
            $redirect = $meta['redirect'] ?? null;
            $isClassMiddleware = $meta['isClass'] ?? false;
            $result = false;

            if (!class_exists($middlewareClass)) {
                $this->recordError($middlewareClass, $message, $onError);
                return $this->handleFailure($message, $onError, $redirect, $response, $flash, $router, $isClassMiddleware);
            }

            $middleware = empty($meta['params'])
                ? $this->container->get($middlewareClass)
                : $this->container->make($middlewareClass, $meta['params']);

            if (!$middleware instanceof MiddlewareInterface) {
                $this->recordError($middlewareClass, 'Middleware must implement MiddlewareInterface', $onError);
                return $this->handleFailure($message, $onError, $redirect, $response, $flash, $router, $isClassMiddleware);
            }

            try {
                $result = $middleware->handle() === true;
            } catch (Throwable $e) {
                $message = $e->getMessage();
                $result = false;
            }

            $this->executed[$middlewareClass][] = $result;

            if (!$result) {
                $this->recordError($middlewareClass, $message, $onError);
                return $this->handleFailure($message, $onError, $redirect, $response, $flash, $router, $isClassMiddleware);
            }
        }

        return null;
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function isAccessible(string $controller, ?string $method = null): bool
    {
        foreach ($this->getMiddlewares($controller, $method) as $meta) {
            $middlewareClass = $meta['class'];

            if (!class_exists($middlewareClass)) {
                return false;
            }

            $middleware = empty($meta['params'])
                ? $this->container->get($middlewareClass)
                : $this->container->make($middlewareClass, $meta['params']);

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
        string        $message,
        string        $onError,
        ?string       $redirect,
        Response      $response,
        Flash         $flash,
        RouterManager $router,
        bool          $isClassMiddleware
    ): ?Response {
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
     * @return array<int, array{
     *     class: class-string,
     *     message: string,
     *     onError: string,
     *     redirect: ?string,
     *     isClass: bool,
     *     params: array<string, mixed>
     * }>
     * @throws ReflectionException
     */
    private function getMiddlewares(string $controller, ?string $method = null): array
    {
        $cacheKey = $controller . '::' . ($method ?? '');

        if (isset($this->middlewareCache[$cacheKey])) {
            return $this->middlewareCache[$cacheKey];
        }

        $all = [];

        $classResults = new ScannerAttribute($controller)
            ->onClass()
            ->scan();

        foreach ($classResults as $entry) {
            if ($entry['attribute'] instanceof MiddlewareAttribute) {
                $i = $entry['attribute'];
                $all[] = [
                    'class' => $i->use,
                    'message' => $i->message,
                    'onError' => $i->onError,
                    'redirect' => $i->redirect,
                    'isClass' => true,
                    'params' => $i->params,
                    'priority' => $i->priority,
                ];
            } elseif ($entry['attribute'] instanceof RateLimit) {
                $all[] = $this->buildRateLimitMeta($entry['attribute'], true);
            } elseif ($entry['attribute'] instanceof IsGranted) {
                $all[] = $this->buildIsGrantedMeta($entry['attribute'], true);
            }
        }

        if ($method) {
            $methodResults = new ScannerAttribute($controller)
                ->onMethods(ReflectionMethod::IS_PUBLIC)
                ->scan();

            foreach ($methodResults as $entry) {
                /** @var array{reflection: ReflectionMethod, attribute: MiddlewareAttribute|RateLimit|IsGranted} $entry */
                $refMethod = $entry['reflection'];

                if ($refMethod->getName() !== $method) {
                    continue;
                }

                if ($entry['attribute'] instanceof MiddlewareAttribute) {
                    $i = $entry['attribute'];
                    $all[] = [
                        'class' => $i->use,
                        'message'  => $i->message,
                        'onError' => $i->onError,
                        'redirect' => $i->redirect,
                        'isClass' => false,
                        'params' => $i->params,
                        'priority' => $i->priority,
                    ];
                } elseif ($entry['attribute'] instanceof RateLimit) {
                    $all[] = $this->buildRateLimitMeta($entry['attribute'], false);
                } elseif ($entry['attribute'] instanceof IsGranted) {
                    $all[] = $this->buildIsGrantedMeta($entry['attribute'], false);
                }
            }
        }

        usort($all, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return $this->middlewareCache[$cacheKey] = $all;
    }

    /**
     * @return array{
     *     class: class-string,
     *     message: string,
     *     onError: string,
     *     redirect: ?string,
     *     isClass: bool,
     *     params: array<string, mixed>
     * }
     */
    private function buildRateLimitMeta(RateLimit $attr, bool $isClass): array
    {
        return [
            'class' => RateLimitMiddleware::class,
            'message' => $attr->message,
            'onError' => 'block',
            'redirect' => null,
            'isClass' => $isClass,
            'params' => [
                'maxAttempts' => $attr->maxAttempts,
                'decaySeconds' => $attr->decaySeconds,
                'message' => $attr->message,
            ],
            'priority' => 0,
        ];
    }

    /**
     * @return array{
     *     class: class-string,
     *     message: string,
     *     onError: string,
     *     redirect: ?string,
     *     isClass: bool,
     *     params: array<string, mixed>
     * }
     */
    private function buildIsGrantedMeta(IsGranted $attr, bool $isClass): array
    {
        return [
            'class' => IsGrantedMiddleware::class,
            'message' => $attr->message !== '' ? $attr->message : 'Access denied.',
            'onError' => $attr->onError,
            'redirect' => $attr->redirect,
            'isClass' => $isClass,
            'params' => [
                'roles' => $attr->roles,
            ],
            'priority' => 0,
        ];
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
            $methodResults = new ScannerAttribute($controller)
                ->onMethods(ReflectionMethod::IS_PUBLIC)
                ->withAttribute(Maintenance::class)
                ->scan();

            foreach ($methodResults as $entry) {
                /** @var array{reflection: ReflectionMethod, attribute: Maintenance} $entry */
                $refMethod = $entry['reflection'];

                if ($refMethod->getName() === $method) {
                    $maintenance = $entry['attribute'];
                    break;
                }
            }
        }

        if ($maintenance === null) {
            $classResults = new ScannerAttribute($controller)
                ->onClass()
                ->withAttribute(Maintenance::class)
                ->scan();

            if (!empty($classResults)) {
                $maintenance = $classResults[0]['attribute'];
            }
        }

        if ($maintenance === null) {
            return null;
        }

        $view = $this->container->get(ViewManager::class);
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