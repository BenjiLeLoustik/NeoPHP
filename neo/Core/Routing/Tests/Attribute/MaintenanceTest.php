<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Tests\Attribute;

use Attribute;
use Neo\Core\Routing\Attribute\Maintenance;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MaintenanceTest extends TestCase
{
    public function testMaintenanceInitializationWithDefaultMessage(): void
    {
        $maintenance = new Maintenance();

        self::assertSame('Maintenance en cours.', $maintenance->message);
    }

    public function testMaintenanceInitializationWithCustomMessage(): void
    {
        $customMessage = 'Le site est en maintenance planifiée jusqu\'à 21h.';
        $maintenance = new Maintenance($customMessage);

        self::assertSame($customMessage, $maintenance->message);
    }

    public function testMaintenanceTargetsClassAndMethod(): void
    {
        $ref = new ReflectionClass(Maintenance::class);
        $attributes = $ref->getAttributes(Attribute::class);

        self::assertNotEmpty($attributes, "L'attribut #[Attribute] est manquant sur la classe Maintenance.");

        $attributeInstance = $attributes[0]->newInstance();

        $expectedFlags = Attribute::TARGET_CLASS | Attribute::TARGET_METHOD;
        self::assertSame($expectedFlags, $attributeInstance->flags);
    }
}