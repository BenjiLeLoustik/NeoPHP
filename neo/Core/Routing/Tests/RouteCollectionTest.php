<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Tests;

use Neo\Core\Routing\Collection\RouteCollection;
use PHPUnit\Framework\TestCase;

final class RouteCollectionTest extends TestCase
{
    public function testAddAndHasRoute(): void
    {
        $collection = new RouteCollection();
        $collection->add('GET', '/users', 'user.index', 'UserController', 'index');

        self::assertTrue($collection->has('GET', '/users'));
        self::assertFalse($collection->has('POST', '/users'));
        self::assertFalse($collection->has('GET', '/missing'));
    }

    public function testAddNormalizesLeadingSlash(): void
    {
        $collection = new RouteCollection();
        $collection->add('GET', 'users', 'user.index', 'UserController', 'index');

        self::assertTrue($collection->has('GET', '/users'));
    }

    public function testAllReturnsAllRegisteredRoutes(): void
    {
        $collection = new RouteCollection();
        $collection->add('GET', '/users', 'user.index', 'UserController', 'index');
        $collection->add('POST', '/users', 'user.store', 'UserController', 'store');
        $collection->add('GET', '/posts', 'post.index', 'PostController', 'index');

        $all = $collection->all();

        self::assertArrayHasKey('GET', $all);
        self::assertArrayHasKey('POST', $all);
        self::assertCount(2, $all['GET']);
        self::assertCount(1, $all['POST']);
    }

    public function testAddOverwritesExistingRouteOnSameMethodAndPath(): void
    {
        $collection = new RouteCollection();
        $collection->add('GET', '/users', 'user.index', 'OldController', 'old');
        $collection->add('GET', '/users', 'user.index', 'NewController', 'new');

        $info = $collection->all()['GET']['/users'];
        self::assertSame('NewController', $info['controller']);
        self::assertSame('new', $info['action']);
    }

    public function testRouteInfoContainsAllFields(): void
    {
        $collection = new RouteCollection();
        $collection->add(
            'GET',
            '/users/{id}',
            'user.show',
            'UserController',
            'show',
            ['id' => '[0-9]+']
        );

        $info = $collection->all()['GET']['/users/{id}'];

        self::assertSame('user.show', $info['name']);
        self::assertSame('UserController', $info['controller']);
        self::assertSame('show', $info['action']);
        self::assertSame(['id' => '[0-9]+'], $info['requirements']);
    }

    public function testToArrayAndFromArrayRoundTrip(): void
    {
        $original = new RouteCollection();
        $original->add('GET', '/users', 'user.index', 'UserController', 'index');
        $original->add('POST', '/users', 'user.store', 'UserController', 'store');

        $restored = RouteCollection::fromArray($original->toArray());

        self::assertSame($original->toArray(), $restored->toArray());
        self::assertTrue($restored->has('GET', '/users'));
        self::assertTrue($restored->has('POST', '/users'));
    }
}