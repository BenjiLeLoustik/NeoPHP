<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Tests;

use Neo\Core\Controller\AbstractController;
use Neo\Core\DI\Container;
use Neo\Core\Http\Request;
use Neo\Core\Http\Response\RedirectResponse;
use Neo\Core\Routing\Router;
use Neo\Core\Routing\RouterControllerExtension;
use PHPUnit\Framework\TestCase;

class RouterControllerExtensionTest extends TestCase
{
    public function testExtendRegistersMethodsOnController(): void
    {
        $container = new Container();

        $routerMock = $this->createMock(Router::class);
        $routerMock->method('generateUrl')->with('login', [])->willReturn('/login');
        $container->set(Router::class, $routerMock);

        $controller = new class($container) extends AbstractController {
            private array $methods = [];
            public function registerMethod(string $name, callable|\Closure $resolver): void {
                $this->methods[$name] = $resolver;
            }
            public function callRegistered(string $name, array $args = []) {
                return $this->methods[$name](...$args);
            }
        };

        $extension = new RouterControllerExtension();
        $extension->extend($controller, $container);

        $path = $controller->callRegistered('getRoutePath', ['login']);
        self::assertSame('/login', $path);

        $response = $controller->callRegistered('redirectToRoute', ['login']);
        self::assertInstanceOf(RedirectResponse::class, $response);
    }
}