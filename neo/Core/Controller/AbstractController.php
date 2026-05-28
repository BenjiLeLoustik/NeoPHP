<?php
declare(strict_types=1);

namespace Neo\Core\Controller;

use Neo\Core\Controller\Exception\AbstractControllerException;
use Neo\Core\DI\Container;
use Neo\Core\Event\Contract\EventInterface;
use Neo\Core\Event\EventDispatcher;
use Neo\Core\Extension\ArrayExtension;
use Neo\Core\Extension\StringExtension;
use Neo\Core\Http\Client\Cookie;
use Neo\Core\Http\Client\Flash;
use Neo\Core\Http\Client\Session;
use Neo\Core\Http\File\Uploader;
use Neo\Core\Http\Request;
use Neo\Core\Http\Response\JsonResponse;
use Neo\Core\Http\Response\RedirectResponse;
use Neo\Core\Http\Response\Response;
use Neo\Core\Routing\Router;
use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\Security\Middleware\MiddlewareHandler;
use Neo\Core\Security\PasswordManager;
use Neo\Core\Utils\Cache\Cache;
use Neo\Core\Utils\Config\Config;
use Neo\Core\Utils\Logger;
use Neo\Core\Utils\Mailer;
use Neo\Core\View\View;

abstract class AbstractController
{
    protected Container $container;
    protected Request $request;
    protected Response $response;
    protected View $view;
    protected MiddlewareHandler $middlewareHandler;
    protected Mailer $mailer;

    public function __construct(?Container $container = null)
    {
        if ($container === null) return;

        $this->container = $container;
        $this->request = $container->get(Request::class);
        $this->response = $container->get(Response::class);
        $this->view = $container->get(View::class);
        $this->middlewareHandler = $container->get(MiddlewareHandler::class);
        $this->mailer = $container->get(Mailer::class);

        $app = array_merge(
            $this->view->getTwig()->getGlobals()['app'] ?? [],
            [
                'session' => $this->getSession(),
                'cookie' => $this->getCookie(),
            ]
        );

        $this->view->getTwig()->addGlobal('app', $app);
    }

    protected function render(string $template, array $params = []): Response
    {
        $content = $this->view->render($template, $params);
        $this->response->setHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->response->setContent($content);
        return $this->response;
    }

    protected function template(string $template, array $params = []): string
    {
        return $this->view->render($template, $params);
    }

    protected function getRoutePath(string $routeName, array $params = []): string
    {
        $router = $this->container->get(Router::class);
        $path = $router->generateUrl($routeName, $params);
        return $path;
    }

    protected function getRedirectBack(?string $fallbackRoute = null, array $routeParams = []): string
    {
        $referer = $this->request->header('Referer');
        if (is_string($referer) && $referer !== '') {
            return $referer;
        }

        return $fallbackRoute ? $this->getRoutePath($fallbackRoute, $routeParams) : '/';
    }

    protected function redirectToRoute(string $routeName, array $params = []): RedirectResponse
    {
        $path = $this->getRoutePath($routeName, $params);
        return new RedirectResponse($path, 302);
    }

    protected function redirectToPath(string $path, int $code = 302): RedirectResponse
    {
        return new RedirectResponse($path, $code);
    }

    protected function redirectBack(
        ?string $fallbackRoute = null,
        array $routeParams = [],
        int $code = 302
    ): RedirectResponse {
        $referer = $this->request->header('Referer');
        if (is_string($referer) && $referer !== '') {
            return new RedirectResponse($referer, $code);
        }

        $previous = $this->request->getPreviousUrl(
            $fallbackRoute ? $this->getRoutePath($fallbackRoute, $routeParams) : '/'
        );

        return new RedirectResponse($previous, $code);
    }

    protected function jsonSuccess(array|object $data = [], int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => $data
        ], $status);
    }

    protected function jsonError(string $message, int $status = 400, array $extra = []): JsonResponse
    {
        return new JsonResponse(array_merge([
            'success' => false,
            'error' => $message,
        ], $extra), $status);
    }

    protected function json(array|object $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }

    protected function getMiddleware(): MiddlewareHandler
    {
        return $this->middlewareHandler;
    }

    protected function getSession(): Session
    {
        return $this->container->get(Session::class);
    }

    protected function getCookie(): Cookie
    {
        return $this->container->get(Cookie::class);
    }

    protected function getFlash(): Flash
    {
        return $this->container->get(Flash::class);
    }

    protected function getLogger(): Logger
    {
        return $this->container->get(Logger::class);
    }

    protected function getCache(): Cache
    {
        return $this->container->get(Cache::class);
    }

    protected function getString(): StringExtension
    {
        return $this->container->get(StringExtension::class);
    }

    protected function getArray(): ArrayExtension
    {
        return $this->container->get(ArrayExtension::class);
    }

    protected function dispatch(EventInterface $event): EventInterface
    {
        return $this->container->get(EventDispatcher::class)->dispatch($event);
    }

    protected function auth(): AuthManager
    {
        return $this->container->get(AuthManager::class);
    }

    protected function getPasswordManager(): PasswordManager
    {
        return $this->container->get(PasswordManager::class);
    }

    protected function getConfig(): Config
    {
        return $this->container->get(Config::class);
    }

    protected function upload(string $field, string $name, array $extensions, string $directory): string
    {
        $uploader = $this->container->get(Uploader::class);
        $file = $this->request->file($field);
        if (!$file) {
            throw new AbstractControllerException(
                title: 'File Upload Error',
                message: sprintf("File field '%s' not found.", $field),
                code: 400
            );
        }

        return $uploader->upload($file, $name, $extensions, $directory);
    }

    protected function getMailer(): Mailer
    {
        return $this->container->get(Mailer::class);
    }
}
