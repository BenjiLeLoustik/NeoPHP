<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware;

use Neo\Core\DI\Container;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Routing\Attribute\RateLimit;
use Neo\Core\Security\Middleware\Attribute\Middleware as MiddlewareAttribute;
use Neo\Core\Security\Middleware\Default\RateLimitMiddleware;
use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;
use Neo\Core\Http\Client\Flash;
use Neo\Core\Http\Response\Response;
use Neo\Core\Routing\Router;
use ReflectionClass;
use Throwable;

class MiddlewareHandler
{
    private Container $container;
    private array $errors = [];
    private array $executed = [];
    private ?string $lastController = null;
    private ?string $lastMethod = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function run(string $controller, ?string $method = null): void
    {
        $this->lastController = $controller;
        $this->lastMethod     = $method;

        $middlewares = $this->getMiddlewares($controller, $method);

        $router   = $this->container->get(Router::class);
        $response = $this->container->get(Response::class);
        $flash    = $this->container->get(Flash::class);

        foreach ($middlewares as $meta) {
            $middlewareClass   = $meta['class'];
            $onError           = $meta['onError'] ?? 'block';
            $message           = $meta['message'] ?? 'Middleware failed';
            $redirect          = $meta['redirect'] ?? null;
            $isClassMiddleware = $meta['isClass'] ?? false;
            $result            = false;

            if (!class_exists($middlewareClass)) {
                $this->recordError($middlewareClass, $message, $onError);
                $this->handleFailure($message, $onError, $redirect, $response, $flash, $router, $isClassMiddleware);
                continue;
            }

            $middleware = empty($meta['params'])
                ? $this->container->get($middlewareClass)
                : $this->container->make($middlewareClass, $meta['params']);

            if (!$middleware instanceof MiddlewareInterface) {
                $this->recordError($middlewareClass, 'Middleware must implement MiddlewareInterface', $onError);
                $this->handleFailure($message, $onError, $redirect, $response, $flash, $router, $isClassMiddleware);
                continue;
            }

            try {
                $result = $middleware->handle() === true;
            } catch (Throwable $e) {
                $message = $e->getMessage();
                $result  = false;
            }

            $this->executed[$middlewareClass][] = $result;

            if (!$result) {
                $this->recordError($middlewareClass, $message, $onError);
                $this->handleFailure($message, $onError, $redirect, $response, $flash, $router, $isClassMiddleware);
            }
        }
    }

    private function handleFailure(
        string $message,
        string $onError,
        ?string $redirect,
        Response $response,
        Flash $flash,
        Router $router,
        bool $isClassMiddleware
    ): void {
        if ($redirect !== null) {
            $flash->add('warning', $message);
            $url = $router->generateUrl($redirect);
            $response->setHeader('Location', $url)->setStatusCode(302)->send();
            exit;
        }

        if ($onError === 'block') {
            throw new FrameworkException(
                title: 'Middleware Error',
                message: $message,
                code: 403
            );
        }

        if ($onError === 'soft') {
            $flash->add('warning', $message);
        }
    }

    private function recordError(string $middleware, string $message, string $onError): void
    {
        $controller = $this->lastController ?? 'unknown';
        $method     = $this->lastMethod     ?? 'unknown';
        $this->errors[$controller][$method][$middleware][] = $message;
    }

    private function getMiddlewares(string $controller, ?string $method = null): array
    {
        $all = [];
        $ref = new ReflectionClass($controller);

        foreach ($ref->getAttributes(MiddlewareAttribute::class) as $attr) {
            $i     = $attr->newInstance();
            $all[] = [
                'class'    => $i->use,
                'message'  => $i->message,
                'onError'  => $i->onError,
                'redirect' => $i->redirect,
                'isClass'  => true,
                'params'   => $i->params,
            ];
        }

        foreach ($ref->getAttributes(RateLimit::class) as $attr) {
            $i     = $attr->newInstance();
            $all[] = $this->buildRateLimitMeta($i, true);
        }

        if ($method && $ref->hasMethod($method)) {
            $refMethod = $ref->getMethod($method);

            foreach ($refMethod->getAttributes(MiddlewareAttribute::class) as $attr) {
                $i     = $attr->newInstance();
                $all[] = [
                    'class'    => $i->use,
                    'message'  => $i->message,
                    'onError'  => $i->onError,
                    'redirect' => $i->redirect,
                    'isClass'  => false,
                    'params'   => $i->params,
                ];
            }

            foreach ($refMethod->getAttributes(RateLimit::class) as $attr) {
                $i     = $attr->newInstance();
                $all[] = $this->buildRateLimitMeta($i, false);
            }
        }

        return $all;
    }

    private function buildRateLimitMeta(RateLimit $attr, bool $isClass): array
    {
        return [
            'class'    => RateLimitMiddleware::class,
            'message'  => $attr->message,
            'onError'  => 'block',
            'redirect' => null,
            'isClass'  => $isClass,
            'params'   => [
                'maxAttempts'  => $attr->maxAttempts,
                'decaySeconds' => $attr->decaySeconds,
                'message'      => $attr->message,
            ],
        ];
    }

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

    public function getMiddleware(string $middlewareClass): array
    {
        return $this->executed[$middlewareClass] ?? [];
    }
}