<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Type;

use DateTime;
use DateTimeInterface;
use Neo\Core\Database\ORM\Platform\AbstractPlatform;

final class DateTimeType extends Type
{
    public function getName(): string {
        return self::DATETIME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'datetime';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTime
    {
        if ($value === null || $value instanceof DateTime) {
            return $value;
        }
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', (string) $value);
        return $dt !== false ? $dt : new DateTime((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        return $value instanceof DateTimeInterface
            ? $value->format('Y-m-d H:i:s')
            : (string) $value;
    }
}