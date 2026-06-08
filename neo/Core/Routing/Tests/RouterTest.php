<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Tests;

use Neo\Core\DI\Container;
use Neo\Core\Routing\Router;
use Neo\Core\Routing\Exception\RouteNotFoundException;
use Neo\Core\Utils\Config\Config;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class RouterTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->set('controllersPath', __DIR__);
        $this->container->set('storagePath', __DIR__);

        $configMock = $this->createMock(Config::class);
        $configMock->method('from')->willReturnSelf();
        $configMock->method('get')->willReturn('dev');
        $this->container->set(Config::class, $configMock);
    }

    public function testUrlGenerationThrowsExceptionIfRouteNotFound(): void
    {
        $router = new Router($this->container);

        $this->expectException(RouteNotFoundException::class);
        $router->generateUrl('route_qui_n_existe_pas');
    }

    public function testGenerateUrlCleansPlaceholders(): void
    {
        $router = new Router($this->container);

        $ref = new ReflectionClass($router);
        $routesProp = $ref->getProperty('routes');
        $routesProp->setAccessible(true);
        $routeCollection = $routesProp->getValue($router);

        $routeCollection->add('GET', '/user/{id}/{name?}', 'user_profile', 'UserController', 'profile');

        $urlWithParams = $router->generateUrl('user_profile', ['id' => 42, 'name' => 'hugo']);
        self::assertSame('/user/42/hugo', $urlWithParams);

        $urlWithOptionalCleaned = $router->generateUrl('user_profile', ['id' => 42]);
        self::assertSame('/user/42', $urlWithOptionalCleaned);
    }
}