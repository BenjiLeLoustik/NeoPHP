<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Type;

use Neo\Core\Database\ORM\Platform\AbstractPlatform;

final class BooleanType extends Type
{
    public function getName(): string {
        return self::BOOLEAN;
    }

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBooleanTypeDeclarationSQL();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?bool
    {
        return $value === null ? null : (bool) $value;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        return $value === null ? null : ((bool) $value ? 1 : 0);
    }
}