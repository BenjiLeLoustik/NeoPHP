<?php
declare(strict_types=1);

namespace Neo\Core\Database\Migration;

use Neo\Core\Database\DatabaseIntrospector;
use Neo\Core\Database\Exception\DatabaseException;

final class MigrationGenerator
{
    public function __construct(
        private readonly DatabaseIntrospector $introspector
    ) {}

    /**
     * @throws DatabaseException
     */
    public function generate(string $migrationsPath, string $name): string
    {
        $tables = $this->introspector->getTables();

        $upLines = [];
        $downLines = [];

        foreach ($tables as $table) {
            if (in_array($table, ['neo_migrations', 'neo_schema_snapshots'], true)) {
                continue;
            }

            $columns = $this->introspector->getColumns($table);
            $upLines[] = $this->executeLine($this->buildCreateTableSql($table, $columns));
            $downLines[] = $this->executeLine("DROP TABLE IF EXISTS `$table`");
        }

        return $this->writeFile(
            $migrationsPath,
            $name,
            implode("\n\n", $upLines),
            implode("\n", array_reverse($downLines))
        );
    }

    /**
     * @param array{
     *     tablesToCreate: array<string, array<int, array<string, mixed>>>,
     *     tablesToDrop: array<string, array<int, array<string, mixed>>>,
     *     tableChanges: array<string, array{added: array<int, array<string,mixed>>, removed: array<int, array<string,mixed>>, modified: array<int, array{before: array<string,mixed>, after: array<string,mixed>}>}>,
     *     tableRenames?: array<int, array{from: string, to: string}>,
     *     columnRenames?: array<string, array<int, array{from: string, to: string}>>
     * } $diff
     */
    public function generateDiff(string $migrationsPath, string $name, array $diff): string
    {
        $upLines = [];
        $downLines = [];

        foreach ($diff['tableRenames'] ?? [] as $rename) {
            $from = $rename['from'];
            $to = $rename['to'];
            $upLines[] = $this->executeLine("RENAME TABLE `$from` TO `$to`");
            $downLines[] = $this->executeLine("RENAME TABLE `$to` TO `$from`");
        }

        foreach ($diff['tablesToCreate'] as $table => $columns) {
            $upLines[] = $this->executeLine($this->buildCreateTableSql($table, $columns));
            $downLines[] = $this->executeLine("DROP TABLE IF EXISTS `$table`");
        }

        foreach ($diff['tablesToDrop'] as $table => $columns) {
            $upLines[] = $this->executeLine("DROP TABLE IF EXISTS `$table`");
            $downLines[] = $this->executeLine($this->buildCreateTableSql($table, $columns));
        }

        foreach ($diff['columnRenames'] ?? [] as $table => $renames) {
            foreach ($renames as $rename) {
                $upLines[] = $this->executeLine("ALTER TABLE `$table` RENAME COLUMN `{$rename['from']}` TO `{$rename['to']}`");
                $downLines[] = $this->executeLine("ALTER TABLE `$table` RENAME COLUMN `{$rename['to']}` TO `{$rename['from']}`");
            }
        }

        foreach ($diff['tableChanges'] as $table => $changes) {
            foreach ($changes['added'] as $col) {
                $upLines[] = $this->executeLine(
                    "ALTER TABLE `$table` ADD COLUMN " . $this->buildColumnDefinition($col)
                );
                $downLines[] = $this->executeLine(
                    "ALTER TABLE `$table` DROP COLUMN `{$col['name']}`"
                );
            }

            foreach ($changes['removed'] as $col) {
                $upLines[] = $this->executeLine(
                    "ALTER TABLE `$table` DROP COLUMN `{$col['name']}`"
                );
                $downLines[] = $this->executeLine(
                    "ALTER TABLE `$table` ADD COLUMN " . $this->buildColumnDefinition($col)
                );
            }

            foreach ($changes['modified'] as $change) {
                $upLines[] = $this->executeLine(
                    "ALTER TABLE `$table` MODIFY COLUMN " . $this->buildColumnDefinition($change['after'])
                );
                $downLines[] = $this->executeLine(
                    "ALTER TABLE `$table` MODIFY COLUMN " . $this->buildColumnDefinition($change['before'])
                );
            }
        }

        return $this->writeFile(
            $migrationsPath,
            $name,
            implode("\n\n", $upLines),
            implode("\n", array_reverse($downLines))
        );
    }

    private function writeFile(string $migrationsPath, string $name, string $upBody, string $downBody): string
    {
        if (!is_dir($migrationsPath)) {
            mkdir($migrationsPath, 0777, true);
        }

        $timestamp = date('Ymd_His');
        $className = 'MigrationVersion_' . $timestamp;
        $file = "$migrationsPath/$className.php";
        $date = date('Y-m-d H:i:s');

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
}
PHP;

        file_put_contents($file, $content);

        return $file;
    }

    /**
     * @param array<int, array<string, mixed>> $columns
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
     * @param array<string, mixed> $col
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

    private function executeLine(string $sql): string
    {
        $escaped = str_replace(["\\", "'"], ["\\\\", "\\'"], $sql);
        return "        \$db->execute('" . $escaped . "');";
    }
}