<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Type;

use Neo\Core\Database\ORM\Platform\AbstractPlatform;

final class DecimalType extends Type
{
    public function getName(): string {
        return self::DECIMAL;
    }

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $precision = (int)($column['precision'] ?? 10);
        $scale = (int)($column['scale'] ?? 0);
        return sprintf('decimal(%d, %d)', $precision, $scale);
    }

    /**
     * @param array<string, mixed> $column
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform, array $column = []): ?string
    {
        return $value === null ? null : (string) $value;
    }
}