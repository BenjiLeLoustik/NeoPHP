<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Type;

use Neo\Core\Database\ORM\Platform\AbstractPlatform;

final class JsonType extends Type
{
    public function getName(): string {
        return self::JSON;
    }

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'json';
    }

    /**
     * @param array<string, mixed> $column
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform, array $column = []): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        return json_decode(is_string($value) ? $value : (string) $value, true);
    }

    /**
     * @param array<string, mixed> $column
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform, array $column = []): ?string
    {
        return $value === null ? null : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}