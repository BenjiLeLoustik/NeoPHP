<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Tests\Attribute;

use Attribute;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Maintenance;
use Neo\Core\Routing\Attribute\RateLimit;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class RoutingAttributesTest extends TestCase
{
    public function testMainRouteAttribute(): void
    {
        $attribute = new MainRoute('/admin/', 'admin_prefix');

        self::assertSame('/admin', $attribute->path);
        self::assertSame('admin_prefix', $attribute->name);

        $ref = new ReflectionClass(MainRoute::class);
        $flags = $ref->getAttributes(Attribute::class)[0]->newInstance()->flags;
        self::assertSame(Attribute::TARGET_CLASS, $flags);
    }

    public function testMaintenanceAttributeWithDefaults(): void
    {
        $attribute = new Maintenance();
        self::assertSame('Maintenance en cours.', $attribute->message);

        $ref = new ReflectionClass(Maintenance::class);
        $flags = $ref->getAttributes(Attribute::class)[0]->newInstance()->flags;
        self::assertSame(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD, $flags);
    }

    public function testRateLimitAttributeWithCustomValues(): void
    {
        $attribute = new RateLimit(maxAttempts: 5, decaySeconds: 30, message: 'Stop !');

        self::assertSame(5, $attribute->maxAttempts);
        self::assertSame(30, $attribute->decaySeconds);
        self::assertSame('Stop !', $attribute->message);

        $ref = new ReflectionClass(RateLimit::class);
        $flags = $ref->getAttributes(Attribute::class)[0]->newInstance()->flags;
        self::assertSame(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS, $flags);
    }
}