<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Type;

use DateTime;
use DateTimeInterface;
use Neo\Core\Database\ORM\Platform\AbstractPlatform;

final class DateType extends Type
{
    public function getName(): string {
        return self::DATE;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'date';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTime
    {
        if ($value === null || $value instanceof DateTime) {
            return $value;
        }
        $dt = DateTime::createFromFormat('Y-m-d', (string) $value);
        return $dt !== false ? $dt : new DateTime((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }
}