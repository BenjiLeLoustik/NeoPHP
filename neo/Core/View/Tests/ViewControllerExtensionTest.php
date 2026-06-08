<?php
declare(strict_types=1);

namespace Neo\Core\View\Tests;

use Neo\Core\Controller\AbstractController;
use Neo\Core\DI\Container;
use Neo\Core\Http\Client\Cookie\Cookie;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Http\Response\Response;
use Neo\Core\View\View;
use Neo\Core\View\ViewControllerExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

class ViewControllerExtensionTest extends TestCase
{
    public function testExtendRegistersRenderAndTemplateMethods(): void
    {
        $container = new Container();

        $sessionMock = $this->createMock(Session::class);
        $cookieMock = $this->createMock(Cookie::class);
        $container->set(Session::class, $sessionMock);
        $container->set(Cookie::class, $cookieMock);

        $responseMock = $this->createMock(Response::class);
        $responseMock->expects(self::once())->method('setHeader')->with('Content-Type', 'text/html; charset=UTF-8');
        $responseMock->expects(self::once())->method('setContent')->with('rendered_content');
        $container->set(Response::class, $responseMock);

        $twigEnvMock = $this->createMock(Environment::class);
        $twigEnvMock->method('getGlobals')->willReturn(['app' => []]);
        $twigEnvMock->expects(self::once())->method('addGlobal');

        $viewMock = $this->createMock(View::class);
        $viewMock->method('render')->with('index.twig', ['foo' => 'bar'])->willReturn('rendered_content');
        $viewMock->method('getTwig')->willReturn($twigEnvMock);
        $container->set(View::class, $viewMock);

        $controller = new class($container) extends AbstractController {
            private array $methods = [];
            public function registerMethod(string $name, callable|\Closure $resolver): void {
                $this->methods[$name] = $resolver;
            }
            public function callRegistered(string $name, array $args = []) {
                return $this->methods[$name](...$args);
            }
        };

        $extension = new ViewControllerExtension();
        $extension->extend($controller, $container);

        $renderResult = $controller->callRegistered('render', ['index.twig', ['foo' => 'bar']]);
        self::assertInstanceOf(Response::class, $renderResult);

        $templateResult = $controller->callRegistered('template', ['index.twig', ['foo' => 'bar']]);
        self::assertSame('rendered_content', $templateResult);
    }
}