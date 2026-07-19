<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Http\Request;
use Neo\Core\Http\Response\RedirectResponse;
use Neo\Core\Routing\RouterManager;

/**
 * @method string getRoutePath(string $routeName, array<string, string> $params = [])
 * @method string getRedirectBack(?string $fallbackRoute = null, array<string, string> $routeParams = [])
 * @method \Neo\Core\Http\Response\RedirectResponse redirectToRoute(string $routeName, array<string, string> $params = [])
 * @method \Neo\Core\Http\Response\RedirectResponse redirectToPath(string $path, int $code = 302)
 * @method \Neo\Core\Http\Response\RedirectResponse redirectBack(?string $fallbackRoute = null, array<string, string> $routeParams = [], int $code = 302)
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class RouterControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getRoutePath', function (
            string $routeName,
            array $params = []
        ) use ($container) {
            return $container->get(RouterManager::class)->generateUrl($routeName, $params);
        });

        $controller->registerMethod('getRedirectBack', function (
            ?string $fallbackRoute = null,
            array $routeParams = []
        ) use ($container) {
            $request = $container->get(Request::class);

            $fallback = $fallbackRoute
                ? $container->get(RouterManager::class)->generateUrl($fallbackRoute, $routeParams)
                : '/';

            return $request->getPreviousUrl($fallback);
        });

        $controller->registerMethod('redirectToRoute', function (
            string $routeName,
            array $params = []
        ) use ($container) {
            $path = $container->get(RouterManager::class)->generateUrl($routeName, $params);
            return new RedirectResponse($path, 302);
        });

        $controller->registerMethod('redirectToPath', function (string $path, int $code = 302) {
            return new RedirectResponse($path, $code);
        });

        $controller->registerMethod('redirectBack', function (
            ?string $fallbackRoute = null,
            array $routeParams = [],
            int $code = 302
        ) use ($container) {
            $request = $container->get(Request::class);
            $referer = $request->header('Referer');

            if (is_string($referer) && $referer !== '') {
                return new RedirectResponse($referer, $code);
            }

            $fallback = $fallbackRoute
                ? $container->get(RouterManager::class)->generateUrl($fallbackRoute, $routeParams)
                : '/';

            return new RedirectResponse($request->getPreviousUrl($fallback), $code);
        });
    }
}