<?php
declare(strict_types=1);

namespace Neo\Core\View;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Http\Client\Cookie\Cookie;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Http\Response\Response;

/**
 * @method \Neo\Core\Http\Response\Response render(string $template, array<string, mixed> $params = [])
 * @method string template(string $template, array<string, mixed> $params = [])
 */
class ViewControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('render', function (
            string $template,
            array $params = []
        ) use ($container) {
            $view = $container->get(View::class);
            $response = $container->get(Response::class);

            $app = array_merge(
                $view->getTwig()->getGlobals()['app'] ?? [],
                [
                    'session' => $container->get(Session::class),
                    'cookie' => $container->get(Cookie::class),
                ]
            );
            $view->getTwig()->addGlobal('app', $app);

            $content = $view->render($template, $params);
            $response->setHeader('Content-Type', 'text/html; charset=UTF-8');
            $response->setContent($content);
            return $response;
        });

        $controller->registerMethod('template', function (
            string $template,
            array $params = []
        ) use ($container) {
            return $container->get(View::class)->render($template, $params);
        });
    }
}