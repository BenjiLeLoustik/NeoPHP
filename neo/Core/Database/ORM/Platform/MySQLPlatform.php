<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Platform;

final class MySQLPlatform extends AbstractPlatform
{
    public function getName(): string
    {
        return 'mysql';
    }

    public function getIntegerTypeDeclarationSQL(array $column): string
    {
        return 'int' . ($this->unsigned($column) ? ' unsigned' : '');
    }

    public function getSmallIntTypeDeclarationSQL(array $column): string
    {
        return 'smallint' . ($this->unsigned($column) ? ' unsigned' : '');
    }

    public function getBigIntTypeDeclarationSQL(array $column): string
    {
        return 'bigint' . ($this->unsigned($column) ? ' unsigned' : '');
    }

    public function getVarcharTypeDeclarationSQL(int $length): string
    {
        return sprintf('varchar(%d)', $length > 0 ? $length : 255);
    }

    public function getBooleanTypeDeclarationSQL(): string
    {
        return 'tinyint(1)';
    }

    public function getIdentitySQL(): string
    {
        return 'AUTO_INCREMENT';
    }

    public function canonicalizeType(string $sqlType): string
    {
        $t = strtolower(trim($sqlType));

        $t = (string) preg_replace('/\s+/', ' ', $t);

        $t = (string) preg_replace_callback(
            '/\b(tinyint|smallint|mediumint|int|integer|bigint)\((\d+)\)/',
            static function (array $m): string {
                if ($m[1] === 'tinyint' && $m[2] === '1') {
                    return 'tinyint(1)';
                }
                return $m[1] === 'integer' ? 'int' : $m[1];
            },
            $t
        );

        $t = (string) preg_replace('/\binteger\b/', 'int', $t);

        if ($t === 'bool' || $t === 'boolean') {
            $t = 'tinyint(1)';
        }

        $t = str_replace('double precision', 'double', $t);

        $t = (string) preg_replace('/,\s+/', ',', $t);

        return $t;
    }

    private function unsigned(array $column): bool
    {
        return !empty($column['unsigned']);
    }
}