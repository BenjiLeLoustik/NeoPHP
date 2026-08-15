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

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'date';
    }

    /**
     * @param mixed $value
     * @param AbstractPlatform $platform
     * @param array<string, mixed> $column
     * @return DateTime|null
     * @throws \DateMalformedStringException
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform, array $column = []): ?DateTime
    {
        if ($value === null || $value instanceof DateTime) {
            return $value;
        }
        $dt = DateTime::createFromFormat('Y-m-d', (string) $value);
        return $dt !== false ? $dt : new DateTime((string) $value);
    }

    /**
     * @param array<string, mixed> $column
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform, array $column = []): ?string
    {
        if ($value === null) {
            return null;
        }
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }
}