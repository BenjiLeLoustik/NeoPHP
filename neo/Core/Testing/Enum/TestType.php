<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Enum;

enum TestType: string
{
    case Unit = 'unit';
    case Feature = 'feature';
    case Database = 'database';
    case Middleware = 'middleware';
    case Auto = 'auto';

    public static function fromNamespace(string $fqcn): self
    {
        return match(true) {
            str_contains($fqcn, 'Repository') => self::Database,
            str_contains($fqcn, 'Controller') => self::Feature,
            str_contains($fqcn, 'Middleware') => self::Middleware,
            default => self::Unit
        };
    }

    public function testCase(): string
    {
        return match($this) {
            self::Database => 'Neo\Core\Testing\DatabaseTestCase',
            self::Feature => 'Neo\Core\Testing\FeatureTestCase',
            self::Middleware => 'Neo\Core\Testing\MiddlewareTestCase',
            default => 'Neo\Core\Testing\TestCase',
        };
    }

    public function testCaseShort(): string
    {
        return match($this) {
            self::Database => 'DatabaseTestCase',
            self::Feature => 'FeatureTestCase',
            self::Middleware => 'MiddlewareTestCase',
            default => 'TestCase',
        };
    }

    public function subDir(): string
    {
        return match($this) {
            self::Database => 'Database',
            self::Feature => 'Feature',
            self::Middleware => 'Middleware',
            default => 'Unit',
        };
    }
}