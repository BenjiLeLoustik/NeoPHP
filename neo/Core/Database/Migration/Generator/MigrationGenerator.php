<?php
declare(strict_types=1);

namespace Neo\Core\Database\Migration\Generator;

use Neo\Core\Database\Access\Introspector\DatabaseIntrospector;
use Neo\Core\Database\Access\Introspector\Metadata\ColumnMetadata;
use Neo\Core\Database\Exception\DatabaseException;

/**
 * @phpstan-type ColumnDef array{
 *     name: string,
 *     type: string,
 *     nullable?: bool,
 *     default?: string|null,
 *     key?: string,
 *     extra?: string
 * }
 */
final class MigrationGenerator
{
    public function __construct(
        private readonly DatabaseIntrospector $introspector
    ) {}

    public function generate(string $migrationsPath, string $name): string
    {
        $tables = $this->introspector->getTables();

        $upLines = [];
        $downLines = [];

        foreach ($tables as $table) {
            if (in_array($table, ['neo_migrations', 'neo_schema_snapshots'], true)) {
                continue;
            }

            $columns = array_map(
                static fn (ColumnMetadata $column): array => $column->toArray(),
                $this->introspector->getColumns($table)
            );
            $upLines[] = $this->guardedCreateTable($table, $columns);
            $downLines[] = $this->guardedDropTable($table);
        }

        return $this->writeFile(
            $migrationsPath,
            $name,
            implode("\n\n", $upLines),
            implode("\n", array_reverse($downLines)),
            usesHelpers: true
        );
    }

    /**
     * @param array<string, mixed> $diff
     */
    public function generateDiff(string $migrationsPath, string $name, array $diff): string
    {
        $upLines = [];
        $downLines = [];

        foreach ($diff['tableRenames'] ?? [] as $rename) {
            $upLines[] = $this->guardedRenameTable($rename['from'], $rename['to']);
            $downLines[] = $this->guardedRenameTable($rename['to'], $rename['from']);
        }

        foreach ($diff['tablesToCreate'] as $table => $columns) {
            $upLines[] = $this->guardedCreateTable($table, $columns);
            $downLines[] = $this->guardedDropTable($table);
        }

        foreach ($diff['tablesToDrop'] as $table => $columns) {
            $upLines[] = $this->guardedDropTable($table);
            $downLines[] = $this->guardedCreateTable($table, $columns);
        }

        foreach ($diff['columnRenames'] ?? [] as $table => $renames) {
            foreach ($renames as $rename) {
                $upLines[] = $this->guardedRenameColumn($table, $rename['from'], $rename['to']);
                $downLines[] = $this->guardedRenameColumn($table, $rename['to'], $rename['from']);
            }
        }

        foreach ($diff['tableChanges'] as $table => $changes) {
            foreach ($changes['added'] as $col) {
                $upLines[] = $this->guardedAddColumn($table, $col);
                $downLines[] = $this->guardedDropColumn($table, $col['name']);
            }

            foreach ($changes['removed'] as $col) {
                $upLines[] = $this->guardedDropColumn($table, $col['name']);
                $downLines[] = $this->guardedAddColumn($table, $col);
            }

            foreach ($changes['modified'] as $change) {
                $upLines[] = $this->guardedModifyColumn($table, $change['after']);
                $downLines[] = $this->guardedModifyColumn($table, $change['before']);
            }
        }

        return $this->writeFile(
            $migrationsPath,
            $name,
            implode("\n\n", $upLines),
            implode("\n", array_reverse($downLines)),
            usesHelpers: true
        );
    }

    /**
     * @param list<ColumnDef> $columns
     */
    private function guardedCreateTable(string $table, array $columns): string
    {
        $sql = $this->buildCreateTableSql($table, $columns);
        $escaped = $this->escape($sql);

        return <<<PHP
        if (!\$this->tableExists(\$db, '{$table}')) {
            \$db->execute('{$escaped}');
        }
PHP;
    }

    private function guardedDropTable(string $table): string
    {
        return <<<PHP
        if (\$this->tableExists(\$db, '{$table}')) {
            \$db->execute('DROP TABLE IF EXISTS `{$table}`');
        }
PHP;
    }

    private function guardedRenameTable(string $from, string $to): string
    {
        return <<<PHP
        if (\$this->tableExists(\$db, '{$from}') && !\$this->tableExists(\$db, '{$to}')) {
            \$db->execute('RENAME TABLE `{$from}` TO `{$to}`');
        }
PHP;
    }

    private function guardedRenameColumn(string $table, string $from, string $to): string
    {
        return <<<PHP
        if (\$this->columnExists(\$db, '{$table}', '{$from}') && !\$this->columnExists(\$db, '{$table}', '{$to}')) {
            \$db->execute('ALTER TABLE `{$table}` RENAME COLUMN `{$from}` TO `{$to}`');
        }
PHP;
    }

    /**
     * @param ColumnDef $col
     */
    private function guardedAddColumn(string $table, array $col): string
    {
        $def = $this->escape($this->buildColumnDefinition($col));

        return <<<PHP
        if (!\$this->columnExists(\$db, '{$table}', '{$col['name']}')) {
            \$db->execute('ALTER TABLE `{$table}` ADD COLUMN {$def}');
        }
PHP;
    }

    private function guardedDropColumn(string $table, string $column): string
    {
        return <<<PHP
        if (\$this->columnExists(\$db, '{$table}', '{$column}')) {
            \$db->execute('ALTER TABLE `{$table}` DROP COLUMN `{$column}`');
        }
PHP;
    }

    /**
     * @param ColumnDef $col
     */
    private function guardedModifyColumn(string $table, array $col): string
    {
        $def = $this->escape($this->buildColumnDefinition($col));

        return <<<PHP
        if (\$this->columnExists(\$db, '{$table}', '{$col['name']}')) {
            \$db->execute('ALTER TABLE `{$table}` MODIFY COLUMN {$def}');
        }
PHP;
    }

    private function writeFile(string $migrationsPath, string $name, string $upBody, string $downBody, bool $usesHelpers = false): string
    {
        if (!is_dir($migrationsPath)) {
            mkdir($migrationsPath, 0777, true);
        }

        $timestamp = date('Ymd_His');
        $className = 'MigrationVersion_' . $timestamp;
        $file = "$migrationsPath/$className.php";
        $date = date('Y-m-d H:i:s');

        $helpers = $usesHelpers ? $this->helperMethods() : '';

        $content = <<<PHP
<?php
declare(strict_types=1);

/**
 * Migration: $name
 * Generated: $date
 */
final class $className
{
    public function up(\Neo\Core\Database\DatabaseManager \$db): void
    {
$upBody
    }

    public function down(\Neo\Core\Database\DatabaseManager \$db): void
    {
$downBody
    }
$helpers
}
PHP;

        file_put_contents($file, $content);

        return $file;
    }

    private function helperMethods(): string
    {
        return <<<'PHP'

    private function tableExists(\Neo\Core\Database\DatabaseManager $db, string $table): bool
    {
        $row = $db->fetch(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
            ['table' => $table]
        );

        return $row !== null;
    }

    private function columnExists(\Neo\Core\Database\DatabaseManager $db, string $table, string $column): bool
    {
        $row = $db->fetch(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
            ['table' => $table, 'column' => $column]
        );

        return $row !== null;
    }
PHP;
    }

    /**
     * @param list<ColumnDef> $columns
     */
    private function buildCreateTableSql(string $table, array $columns): string
    {
        $defs = [];
        $primary = [];

        foreach ($columns as $col) {
            $defs[] = '        ' . $this->buildColumnDefinition($col);

            if (($col['key'] ?? '') === 'PRI') {
                $primary[] = "`{$col['name']}`";
            }
        }

        if (!empty($primary)) {
            $defs[] = '        PRIMARY KEY (' . implode(', ', $primary) . ')';
        }

        $colsSql = implode(",\n", $defs);

        return "CREATE TABLE IF NOT EXISTS `$table` (\n$colsSql\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    /**
     * @param ColumnDef $col
     */
    private function buildColumnDefinition(array $col): string
    {
        $def = "`{$col['name']}` {$col['type']}";

        if (empty($col['nullable'])) {
            $def .= ' NOT NULL';
        }

        $isCurrentTimestampDefault = $col['default'] !== null
            && strtoupper((string) $col['default']) === 'CURRENT_TIMESTAMP';

        if ($col['default'] !== null) {
            $def .= $isCurrentTimestampDefault
                ? ' DEFAULT CURRENT_TIMESTAMP'
                : " DEFAULT '{$col['default']}'";
        }

        $extra = trim((string) preg_replace('/DEFAULT_GENERATED/i', '', (string) $col['extra']));

        if (strcasecmp($extra, 'auto_increment') === 0) {
            $def .= ' AUTO_INCREMENT';
        } elseif ($extra !== '') {
            $def .= ' ' . strtoupper($extra);
        }

        return $def;
    }

    private function escape(string $sql): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $sql);
    }
}