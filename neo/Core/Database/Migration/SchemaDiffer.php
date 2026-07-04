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
            && empty($diff['tableChanges'])
            && empty($diff['tableRenames']);
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $tablesToCreate
     * @param array<string, array<int, array<string, mixed>>> $tablesToDrop
     * @return array<int, array{from: string, to: string}>
     */
    public function findTableRenameCandidates(array $tablesToCreate, array $tablesToDrop): array
    {
        $dropSignatures = [];
        foreach ($tablesToDrop as $table => $columns) {
            $dropSignatures[$this->buildTableSignature($columns)][] = $table;
        }

        $createSignatures = [];
        foreach ($tablesToCreate as $table => $columns) {
            $createSignatures[$this->buildTableSignature($columns)][] = $table;
        }

        $candidates = [];

        foreach ($dropSignatures as $signature => $droppedTables) {
            if (count($droppedTables) !== 1 || empty($createSignatures[$signature]) || count($createSignatures[$signature]) !== 1) {
                continue; // ambiguous match on either side, skip
            }

            $candidates[] = [
                'from' => $droppedTables[0],
                'to' => $createSignatures[$signature][0],
            ];
        }

        return $candidates;
    }

    /**
     * @param array<int, array<string, mixed>> $removed
     * @param array<int, array<string, mixed>> $added
     * @return array<int, array{from: string, to: string}>
     */
    public function findColumnRenameCandidates(array $removed, array $added): array
    {
        $removedBySignature = [];
        foreach ($removed as $col) {
            $removedBySignature[$this->signature($col)][] = $col['name'];
        }

        $addedBySignature = [];
        foreach ($added as $col) {
            $addedBySignature[$this->signature($col)][] = $col['name'];
        }

        $candidates = [];

        foreach ($removedBySignature as $signature => $names) {
            if (count($names) !== 1 || empty($addedBySignature[$signature]) || count($addedBySignature[$signature]) !== 1) {
                continue;
            }

            $candidates[] = [
                'from' => $names[0],
                'to' => $addedBySignature[$signature][0],
            ];
        }

        return $candidates;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $tablesToCreate
     * @param array<string, array{added: array<int, array<string,mixed>>, removed: array<int, array<string,mixed>>, modified: array<int, array{before: array<string,mixed>, after: array<string,mixed>}>}> $tableChanges
     * @return array<int, array{table: string, column: string, context: string}>
     */
    public function findRiskyNotNullChanges(array $tablesToCreate, array $tableChanges): array
    {
        $risks = [];

        foreach ($tableChanges as $table => $changes) {
            foreach ($changes['added'] as $col) {
                if (empty($col['nullable']) && $col['default'] === null && !$this->isAutoIncrementPrimary($col)) {
                    $risks[] = ['table' => $table, 'column' => $col['name'], 'context' => 'added'];
                }
            }

            foreach ($changes['modified'] as $change) {
                $before = $change['before'];
                $after = $change['after'];

                if (!empty($before['nullable']) && empty($after['nullable']) && $after['default'] === null) {
                    $risks[] = ['table' => $table, 'column' => $after['name'], 'context' => 'modified'];
                }
            }
        }

        return $risks;
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

    /**
     * @param array<int, array<string, mixed>> $columns
     */
    private function buildTableSignature(array $columns): string
    {
        $parts = [];

        foreach ($columns as $col) {
            $parts[$col['name']] = $col['name'] . ':' . $this->signature($col);
        }

        ksort($parts);

        return implode('|', $parts);
    }

    /**
     * @param array<string, mixed> $col
     */
    private function isAutoIncrementPrimary(array $col): bool
    {
        return ($col['key'] ?? '') === 'PRI'
            && str_contains(strtolower((string) ($col['extra'] ?? '')), 'auto_increment');
    }
}