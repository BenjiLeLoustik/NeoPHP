<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Type;

use Neo\Core\Database\ORM\Platform\AbstractPlatform;

final class FloatType extends Type
{
    public function getName(): string {
        return self::FLOAT;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'double';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?float
    {
        return $value === null ? null : (float) $value;
    }
}