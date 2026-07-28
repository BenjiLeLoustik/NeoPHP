<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Type;

use Neo\Core\Database\ORM\Platform\AbstractPlatform;

final class SmallIntType extends Type
{
    public function getName(): string {
        return self::SMALLINT;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getSmallIntTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?int
    {
        return $value === null ? null : (int) $value;
    }
}