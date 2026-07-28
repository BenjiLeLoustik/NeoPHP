<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Type;

use Neo\Core\Database\Exception\DatabaseException;

final class TypeRegistry
{
    private static array $types = [];

    private static bool $initialized = false;

    private static function boot(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        foreach ([
                     new StringType(),
                     new TextType(),
                     new IntegerType(),
                     new SmallIntType(),
                     new BigIntType(),
                     new BooleanType(),
                     new FloatType(),
                     new DecimalType(),
                     new DateTimeType(),
                     new DateType(),
                     new TimeType(),
                     new JsonType(),
                 ] as $type) {
            self::$types[$type->getName()] = $type;
        }
    }

    public static function register(Type $type): void
    {
        self::boot();
        self::$types[$type->getName()] = $type;
    }

    public static function get(string $name): Type
    {
        self::boot();

        if (!isset(self::$types[$name])) {
            throw new DatabaseException(
                title: 'Unknown ORM Type',
                message: sprintf("No type registered under name '%s'.", $name),
                code: 500
            );
        }

        return self::$types[$name];
    }

    public static function has(string $name): bool
    {
        self::boot();
        return isset(self::$types[$name]);
    }
}