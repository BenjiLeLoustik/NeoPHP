<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Type;

use Neo\Core\Database\ORM\Platform\AbstractPlatform;

final class StringType extends Type
{
    public function getName(): string
    {
        return self::STRING;
    }

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getVarcharTypeDeclarationSQL((int) ($column['length'] ?? 255));
    }

    /**
     * @param array<string, mixed> $column
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform, array $column = []): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * @param array<string, mixed> $column
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform, array $column = []): ?string
    {
        return $value === null ? null : (string) $value;
    }
}