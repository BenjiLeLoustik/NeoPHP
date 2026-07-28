<?php

namespace Neo\Core\Database\ORM\Platform;

abstract class AbstractPlatform
{
    abstract public function getName(): string;

    public function quoteIdentifier(string $identifier): string
    {
        return '`'. str_replace('`', '``', $identifier) .'`';
    }

    /**
     * @param array<string, mixed> $column
     */
    abstract public function getIntegerTypeDeclarationSQL(array $column): string;

    /**
     * @param array<string, mixed> $column
     */
    abstract public function getSmallIntTypeDeclarationSQL(array $column): string;

    /**
     * @param array<string, mixed> $column
     */
    abstract public function getBigIntTypeDeclarationSQL(array $column): string;

    abstract public function getVarcharTypeDeclarationSQL(int $length): string;

    abstract public function getBooleanTypeDeclarationSQL(): string;

    abstract public function getIdentitySQL(): string;

    abstract public function canonicalizeType(string $sqlType): string;
}