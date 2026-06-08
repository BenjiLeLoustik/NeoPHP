<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Tests;

use Neo\Core\Routing\Router;
use Neo\Core\Routing\RouterViewExtension;
use PHPUnit\Framework\TestCase;

class RouterViewExtensionTest extends TestCase
{
    public function testGetFunctionsExposesPathAndCurrentRoute(): void
    {
        $routerMock = $this->createMock(Router::class);
        $routerMock->method('generateUrl')->with('home', ['id' => 1])->willReturn('/home/1');
        $routerMock->method('getCurrentRouteName')->willReturn('home');

        $extension = new RouterViewExtension($routerMock);
        $functions = $extension->getFunctions();

        self::assertArrayHasKey('path', $functions);
        self::assertArrayHasKey('currentRoute', $functions);

        $pathCallable = $functions['path']['callable'];
        self::assertSame('/home/1', $pathCallable('home', ['id' => 1]));

        $currentRouteCallable = $functions['currentRoute']['callable'];
        self::assertSame('home', $currentRouteCallable());

        self::assertEmpty($extension->getFilters());
    }
}