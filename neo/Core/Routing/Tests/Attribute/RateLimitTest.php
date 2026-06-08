<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Tests\Attribute;

use Attribute;
use Neo\Core\Routing\Attribute\RateLimit;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class RateLimitTest extends TestCase
{
    public function testRateLimitInitializationWithDefaultValues(): void
    {
        $rateLimit = new RateLimit();

        self::assertSame(60, $rateLimit->maxAttempts);
        self::assertSame(60, $rateLimit->decaySeconds);
        self::assertSame(
            'Trop de requêtes, veuillez réessayer dans quelques instants.',
            $rateLimit->message
        );
    }

    public function testRateLimitInitializationWithCustomValues(): void
    {
        $rateLimit = new RateLimit(
            maxAttempts: 10,
            decaySeconds: 30,
            message: 'Ralentissez la cadence !'
        );

        self::assertSame(10, $rateLimit->maxAttempts);
        self::assertSame(30, $rateLimit->decaySeconds);
        self::assertSame('Ralentissez la cadence !', $rateLimit->message);
    }

    public function testRateLimitTargetsMethodAndClass(): void
    {
        $ref = new ReflectionClass(RateLimit::class);
        $attributes = $ref->getAttributes(Attribute::class);

        self::assertNotEmpty($attributes, "L'attribut #[Attribute] est manquant sur la classe RateLimit.");

        $attributeInstance = $attributes[0]->newInstance();

        $expectedFlags = Attribute::TARGET_METHOD | Attribute::TARGET_CLASS;
        self::assertSame($expectedFlags, $attributeInstance->flags);
    }
}