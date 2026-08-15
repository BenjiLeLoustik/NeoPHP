<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Type;

use Neo\Core\Database\ORM\Platform\AbstractPlatform;

final class BigIntType extends Type
{
    public function getName(): string {
        return self::BIGINT;
    }

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBigIntTypeDeclarationSQL($column);
    }

    /**
     * @param array<string, mixed> $column
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform, array $column = []): ?int
    {
        return $value === null ? null : (int) $value;
    }
}