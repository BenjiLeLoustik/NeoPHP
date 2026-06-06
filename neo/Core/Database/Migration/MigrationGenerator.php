<?php
declare(strict_types=1);

namespace Neo\Core\Database\Migration;

use Neo\Core\Database\DatabaseIntrospector;

final class MigrationGenerator
{
    public function __construct(private DatabaseIntrospector $introspector) {}

    public function generate(string $migrationsPath, string $name): string
    {
        if (!is_dir($migrationsPath)) {
            mkdir($migrationsPath, 0777, true);
        }

        $timestamp = date('Ymd_His');
        $className = 'MigrationVersion_' . $timestamp;
        $file = "$migrationsPath/$className.php";

        $tables = $this->introspector->getTables();

        $upBody = $this->buildUpBody($tables);
        $downBody = $this->buildDownBody($tables);

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

    private function buildUpBody(array $tables): string
    {
        $lines = [];

        foreach ($tables as $table) {
            if (in_array($table, ['neo_migrations', 'neo_schema_snapshots'], true)) {
                continue;
            }

            $columns = $this->introspector->getColumns($table);
            $defs = [];
            $primary = [];

            foreach ($columns as $col) {
                $def = "        `{$col['name']}` {$col['type']}";

                if (!$col['nullable']) {
                    $def .= ' NOT NULL';
                }

                if ($col['default'] !== null) {
                    $def .= " DEFAULT '{$col['default']}'";
                }

                if ($col['extra']) {
                    $def .= ' ' . strtoupper($col['extra']);
                }

                $defs[] = $def;

                if ($col['key'] === 'PRI') {
                    $primary[] = "`{$col['name']}`";
                }
            }

            if (!empty($primary)) {
                $defs[] = '        PRIMARY KEY (' . implode(', ', $primary) . ')';
            }

            $colsSql = implode(",\n", $defs);
            $sql = "CREATE TABLE IF NOT EXISTS `$table` (\n$colsSql\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $escaped = str_replace(["\\", "'"], ["\\\\", "\\'"], $sql);
            $lines[] = "        \$db->execute('" . $escaped . "');";
        }

        return implode("\n\n", $lines);
    }

    private function buildDownBody(array $tables): string
    {
        $lines = [];
        $reversed = array_reverse(array_filter($tables, fn($t) => $t !== 'neo_migrations'));

        foreach ($reversed as $table) {
            $lines[] = "        \$db->execute('DROP TABLE IF EXISTS `$table`');";
        }

        return implode("\n", $lines);
    }
}