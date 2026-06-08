<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Tests;

use Neo\Core\Routing\RouteCollection;
use PHPUnit\Framework\TestCase;

class RouteCollectionTest extends TestCase
{
    public function testAddAndHasRoute(): void
    {
        $collection = new RouteCollection();

        $collection->add(
            method: 'GET',
            path: 'blog/article',
            name: 'blog_show',
            controller: 'BlogController',
            action: 'show',
            requirements: ['id' => '\d+']
        );

        self::assertTrue($collection->has('GET', '/blog/article'));
        self::assertFalse($collection->has('POST', '/blog/article'));

        $all = $collection->all();
        self::assertArrayHasKey('GET', $all);
        self::assertArrayHasKey('/blog/article', $all['GET']);

        self::assertSame('blog_show', $all['GET']['/blog/article']['name']);
        self::assertSame('BlogController', $all['GET']['/blog/article']['controller']);
        self::assertSame('show', $all['GET']['/blog/article']['action']);
        self::assertSame(['id' => '\d+'], $all['GET']['/blog/article']['requirements']);
    }
}