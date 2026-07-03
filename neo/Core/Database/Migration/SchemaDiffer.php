<?php
declare(strict_types=1);

namespace Neo\Core\Database\Migration;

final class SchemaDiffer
{
    /**
     * @param array<string, array<int, array<string, mixed>>> $previous
     * @param array<string, array<int, array<string, mixed>>> $current
     * @return array{
     *     tablesToCreate: array<string, array<int, array<string, mixed>>>,
     *     tablesToDrop: array<string, array<int, array<string, mixed>>>,
     *     tableChanges: array<string, array{
     *         added: array<int, array<string, mixed>>,
     *         removed: array<int, array<string, mixed>>,
     *         modified: array<int, array{before: array<string, mixed>, after: array<string, mixed>}>
     *     }>
     * }
     */
    public function diff(array $previous, array $current): array
    {
        $tablesToCreate = [];
        foreach (array_diff(array_keys($current), array_keys($previous)) as $table) {
            $tablesToCreate[$table] = $current[$table];
        }

        $tablesToDrop = [];
        foreach (array_diff(array_keys($previous), array_keys($current)) as $table) {
            $tablesToDrop[$table] = $previous[$table];
        }

        $tableChanges = [];

        foreach ($current as $table => $columns) {
            if (!isset($previous[$table])) {
                continue;
            }

            $prevColumns = $this->indexByName($previous[$table]);
            $currColumns = $this->indexByName($columns);

            $added = [];
            $removed = [];
            $modified = [];

            foreach ($currColumns as $name => $col) {
                if (!isset($prevColumns[$name])) {
                    $added[] = $col;
                    continue;
                }

                if ($this->signature($prevColumns[$name]) !== $this->signature($col)) {
                    $modified[] = ['before' => $prevColumns[$name], 'after' => $col];
                }
            }

            foreach ($prevColumns as $name => $col) {
                if (!isset($currColumns[$name])) {
                    $removed[] = $col;
                }
            }

            if ($added || $removed || $modified) {
                $tableChanges[$table] = [
                    'added' => $added,
                    'removed' => $removed,
                    'modified' => $modified,
                ];
            }
        }

        return [
            'tablesToCreate' => $tablesToCreate,
            'tablesToDrop' => $tablesToDrop,
            'tableChanges' => $tableChanges,
        ];
    }

    /**
     * @param array{tablesToCreate: array<string, mixed>, tablesToDrop: array<string, mixed>, tableChanges: array<string, mixed>} $diff
     */
    public function isEmpty(array $diff): bool
    {
        return empty($diff['tablesToCreate'])
            && empty($diff['tablesToDrop'])
            && empty($diff['tableChanges']);
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     * @return array<string, array<string, mixed>>
     */
    private function indexByName(array $columns): array
    {
        $indexed = [];
        foreach ($columns as $col) {
            $indexed[$col['name']] = $col;
        }
        return $indexed;
    }

    /**
     * @param array<string, mixed> $col
     */
    private function signature(array $col): string
    {
        return implode('|', [
            (string) ($col['type'] ?? ''),
            !empty($col['nullable']) ? '1' : '0',
            (string) ($col['default'] ?? ''),
            (string) ($col['extra'] ?? ''),
            (string) ($col['key'] ?? ''),
        ]);
    }
}