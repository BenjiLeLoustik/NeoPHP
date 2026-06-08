<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Tests\Attribute;

use Neo\Core\Routing\Attribute\Route;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class RouteTest extends TestCase
{
    public function testRouteAttributeInitializationWithDefaults(): void
    {
        $route = new Route('/home', 'home_index');

        self::assertSame('/home', $route->path);
        self::assertSame('home_index', $route->name);
        self::assertSame(['GET'], $route->methods, 'La méthode par défaut doit être GET');
        self::assertSame([], $route->requirements, 'Les contraintes par défaut doivent être un tableau vide');
    }

    public function testRouteAttributeInitializationWithCustomValues(): void
    {
        $route = new Route(
            path: '/users/{id}',
            name: 'users_show',
            methods: ['GET', 'POST'],
            requirements: ['id' => '\d+']
        );

        self::assertSame('/users/{id}', $route->path);
        self::assertSame('users_show', $route->name);
        self::assertSame(['GET', 'POST'], $route->methods);
        self::assertSame(['id' => '\d+'], $route->requirements);
    }

    public function testRouteIsTargetingMethodsOnly(): void
    {
        $ref = new ReflectionClass(Route::class);
        $attributes = $ref->getAttributes(\Attribute::class);

        self::assertNotEmpty($attributes, "L'attribut #[Attribute] est manquant sur la classe Route.");

        $attributeInstance = $attributes[0]->newInstance();

        self::assertSame(\Attribute::TARGET_METHOD, $attributeInstance->flags);
    }
}