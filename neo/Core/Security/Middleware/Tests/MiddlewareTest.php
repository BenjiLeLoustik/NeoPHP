<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Tests;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\Security\Middleware\Attribute\Middleware as MiddlewareAttribute;
use Neo\Core\Security\Middleware\Default\AuthMiddleware;
use Neo\Core\Security\Middleware\Default\ExampleMiddleware;
use Neo\Core\Security\Middleware\Default\GuestMiddleware;
use Neo\Core\Security\Middleware\Default\RoleMiddleware;
use PHPUnit\Framework\TestCase;

final class MiddlewareTest extends TestCase
{
    public function testAttributeStoresConstructorValues(): void
    {
        $attr = new MiddlewareAttribute(
            use: AuthMiddleware::class,
            message: 'Not authenticated',
            onError: 'redirect',
            redirect: 'login',
            params: ['foo' => 'bar'],
            priority: 10,
        );

        self::assertSame(AuthMiddleware::class, $attr->use);
        self::assertSame('Not authenticated', $attr->message);
        self::assertSame('redirect', $attr->onError);
        self::assertSame('login', $attr->redirect);
        self::assertSame(['foo' => 'bar'], $attr->params);
        self::assertSame(10, $attr->priority);
    }

    public function testAttributeDefaultValues(): void
    {
        $attr = new MiddlewareAttribute(use: AuthMiddleware::class);

        self::assertSame('', $attr->message);
        self::assertSame('block', $attr->onError);
        self::assertNull($attr->redirect);
        self::assertSame([], $attr->params);
        self::assertSame(0, $attr->priority);
    }

    public function testExampleMiddlewareAlwaysReturnsFalse(): void
    {
        self::assertFalse(new ExampleMiddleware()->handle());
    }

    /**
     * @throws ContainerException
     */
    public function testAuthMiddlewareReturnsTrueWhenUserIsAuthenticated(): void
    {
        $auth = $this->createMock(AuthManager::class);
        $auth->method('check')->willReturn(true);

        $container = new Container();
        $container->instance(AuthManager::class, $auth);

        self::assertTrue(new AuthMiddleware($container)->handle());
    }

    /**
     * @throws ContainerException
     */
    public function testAuthMiddlewareReturnsFalseWhenUserIsNotAuthenticated(): void
    {
        $auth = $this->createMock(AuthManager::class);
        $auth->method('check')->willReturn(false);

        $container = new Container();
        $container->instance(AuthManager::class, $auth);

        self::assertFalse(new AuthMiddleware($container)->handle());
    }

    /**
     * @throws ContainerException
     */
    public function testGuestMiddlewareReturnsTrueWhenUserIsNotAuthenticated(): void
    {
        $auth = $this->createMock(AuthManager::class);
        $auth->method('check')->willReturn(false);

        $container = new Container();
        $container->instance(AuthManager::class, $auth);

        self::assertTrue(new GuestMiddleware($container)->handle());
    }

    /**
     * @throws ContainerException
     */
    public function testGuestMiddlewareReturnsFalseWhenUserIsAuthenticated(): void
    {
        $auth = $this->createMock(AuthManager::class);
        $auth->method('check')->willReturn(true);

        $container = new Container();
        $container->instance(AuthManager::class, $auth);

        self::assertFalse(new GuestMiddleware($container)->handle());
    }

    /**
     * @throws ContainerException
     */
    public function testRoleMiddlewareReturnsTrueWhenUserHasRole(): void
    {
        $auth = $this->createMock(AuthManager::class);
        $auth->method('hasRole')->with('admin')->willReturn(true);

        $container = new Container();
        $container->instance(AuthManager::class, $auth);

        self::assertTrue(new RoleMiddleware($container, 'admin')->handle());
    }

    /**
     * @throws ContainerException
     */
    public function testRoleMiddlewareReturnsFalseWhenUserLacksRole(): void
    {
        $auth = $this->createMock(AuthManager::class);
        $auth->method('hasRole')->with('admin')->willReturn(false);

        $container = new Container();
        $container->instance(AuthManager::class, $auth);

        self::assertFalse(new RoleMiddleware($container, 'admin')->handle());
    }

    /**
     * @throws ContainerException
     */
    public function testRoleMiddlewareUsesDefaultRoleAdmin(): void
    {
        $auth = $this->createMock(AuthManager::class);
        $auth->expects(self::once())
            ->method('hasRole')
            ->with('admin')
            ->willReturn(true);

        $container = new Container();
        $container->instance(AuthManager::class, $auth);

        new RoleMiddleware($container)->handle();
    }
}