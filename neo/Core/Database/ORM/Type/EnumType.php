<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Type;

use Neo\Core\Database\ORM\Platform\AbstractPlatform;

final class EnumType extends Type
{
    public function getName(): string
    {
        return self::ENUM;
    }

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $values = $this->resolveValues($column);

        $quoted = array_map(
            static fn(string $v): string => "'" . str_replace("'", "''", $v) . "'",
            $values
        );

        return 'ENUM(' . implode(', ', $quoted) . ')';
    }

    /**
     * @param array<string, mixed> $column
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform, array $column = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $enumClass = $column['enumClass'] ?? null;

        if (is_string($enumClass) && enum_exists($enumClass) && is_subclass_of($enumClass, \BackedEnum::class)) {
            return $enumClass::from($value);
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $column
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform, array $column = []): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $column
     * @return list<string>
     */
    private function resolveValues(array $column): array
    {
        $enumClass = $column['enumClass'] ?? null;

        if (is_string($enumClass) && enum_exists($enumClass) && is_subclass_of($enumClass, \BackedEnum::class)) {
            return array_map(static fn(\BackedEnum $case): string => (string) $case->value, $enumClass::cases());
        }

        return $column['values'] ?? [];
    }
}