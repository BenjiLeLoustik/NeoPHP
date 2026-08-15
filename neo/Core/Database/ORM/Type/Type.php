<?php

declare(strict_types=1);

namespace Neo\Core\Database\ORM\Type;

use Neo\Core\Database\ORM\Platform\AbstractPlatform;

abstract class Type
{
    public const string STRING = 'string';
    public const string TEXT = 'text';
    public const string INTEGER = 'integer';
    public const string SMALLINT = 'smallint';
    public const string BIGINT = 'bigint';
    public const string BOOLEAN = 'boolean';
    public const string FLOAT = 'float';
    public const string DECIMAL = 'decimal';
    public const string DATETIME = 'datetime';
    public const string DATE = 'date';
    public const string TIME = 'time';
    public const string JSON = 'json';
    public const string ENUM = 'enum';

    abstract public function getName(): string;

    /**
     * @param array<string, mixed> $column
     */
    abstract public function getSQLDeclaration(array $column, AbstractPlatform $platform): string;

    /**
     * @param array<string, mixed> $column
     */
    public function convertToDatabaseValue(
        mixed $value,
        AbstractPlatform $platform,
        array $column = [],
    ): mixed
    {
        return $value;
    }

    /**
     * @param array<string, mixed> $column
     */
    public function convertToPHPValue(
        mixed $value,
        AbstractPlatform $platform,
        array $column = []
    ): mixed
    {
        return $value;
    }
}