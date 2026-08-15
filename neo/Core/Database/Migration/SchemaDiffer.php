<?php
declare(strict_types=1);

namespace Neo\Core\Database\Migration;

/**
 * @phpstan-type ColumnDef array<string, mixed>
 * @phpstan-type Schema array<string, list<ColumnDef>>
 * @phpstan-type ForeignKeyDef array{table: string, column: string, referencedTable: string, referencedColumn: string, onDelete: string|null, onUpdate: string|null}
 */
final class SchemaDiffer
{
    /**
     * @param Schema $previous
     * @param Schema $current
     * @return array<string, mixed>
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
     * @param array<string, list<ForeignKeyDef>> $previous
     * @param array<string, list<ForeignKeyDef>> $current
     * @return array{add: array<string, list<ForeignKeyDef>>, remove: array<string, list<ForeignKeyDef>>}
     */
    public function diffForeignKeys(array $previous, array $current): array
    {
        $add = [];
        $remove = [];

        foreach ($current as $table => $fks) {
            $prevFks = $this->indexByColumn($previous[$table] ?? []);
            $currFks = $this->indexByColumn($fks);

            foreach ($currFks as $column => $fk) {
                if (!isset($prevFks[$column]) || $this->fkSignature($prevFks[$column]) !== $this->fkSignature($fk)) {
                    $add[$table][] = $fk;
                }
            }
        }

        foreach ($previous as $table => $fks) {
            $prevFks = $this->indexByColumn($fks);
            $currFks = $this->indexByColumn($current[$table] ?? []);

            foreach ($prevFks as $column => $fk) {
                if (!isset($currFks[$column]) || $this->fkSignature($currFks[$column]) !== $this->fkSignature($fk)) {
                    $remove[$table][] = $fk;
                }
            }
        }

        return ['add' => $add, 'remove' => $remove];
    }

    /**
     * @param array<string, mixed> $diff
     */
    public function isEmpty(array $diff): bool
    {
        return empty($diff['tablesToCreate'])
            && empty($diff['tablesToDrop'])
            && empty($diff['tableChanges'])
            && empty($diff['tableRenames'])
            && empty($diff['columnRenames'])
            && empty($diff['foreignKeysToAdd'])
            && empty($diff['foreignKeysToDrop']);
    }

    /**
     * @param Schema $tablesToCreate
     * @param Schema $tablesToDrop
     * @return list<array{from: string, to: string}>
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
                continue;
            }

            $candidates[] = [
                'from' => array_first($droppedTables),
                'to' => array_first($createSignatures[$signature]),
            ];
        }

        return $candidates;
    }

    /**
     * @param list<ColumnDef> $removed
     * @param list<ColumnDef> $added
     * @return list<array{from: string, to: string}>
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
                'from' => array_first($names),
                'to' => array_first($addedBySignature[$signature]),
            ];
        }

        return $candidates;
    }

    /**
     * @param Schema $tablesToCreate
     * @param array<string, mixed> $tableChanges
     * @return list<array{table: string, column: string, context: string}>
     */
    public function findRiskyNotNullChanges(array $tablesToCreate, array $tableChanges): array
    {
        $risks = [];

        foreach ($tableChanges as $table => $changes) {
            foreach ($changes['added'] as $col) {
                if (empty($col['nullable']) && $col['default'] === null && !$this->isAutoIncrementPrimary($col)) {
                    $risks[] = [
                        'table' => $table,
                        'column' => $col['name'],
                        'context' => 'added'
                    ];
                }
            }

            foreach ($changes['modified'] as $change) {
                $before = $change['before'];
                $after = $change['after'];

                if (!empty($before['nullable']) && empty($after['nullable']) && $after['default'] === null) {
                    $risks[] = [
                        'table' => $table,
                        'column' => $after['name'],
                        'context' => 'modified'
                    ];
                }
            }
        }

        return $risks;
    }

    /**
     * @param list<ColumnDef> $columns
     * @return array<string, ColumnDef>
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
     * @param list<ForeignKeyDef> $fks
     * @return array<string, ForeignKeyDef>
     */
    private function indexByColumn(array $fks): array
    {
        $indexed = [];
        foreach ($fks as $fk) {
            $indexed[$fk['column']] = $fk;
        }
        return $indexed;
    }

    /**
     * @param ForeignKeyDef $fk
     */
    private function fkSignature(array $fk): string
    {
        return implode('|', [
            $fk['referencedTable'],
            $fk['referencedColumn'],
            strtoupper((string) ($fk['onDelete'] ?? '')) ?: 'RESTRICT',
            strtoupper((string) ($fk['onUpdate'] ?? '')) ?: 'RESTRICT',
        ]);
    }

    /**
     * @param ColumnDef $col
     */
    private function signature(array $col): string
    {
        return implode('|', [
            (string)($col['type'] ?? ''),
            !empty($col['nullable']) ? '1' : '0',
            (string)($col['default'] ?? ''),
            (string)($col['extra'] ?? ''),
            (string)($col['key'] ?? ''),
        ]);
    }

    /**
     * @param list<ColumnDef> $columns
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
     * @param ColumnDef $col
     */
    private function isAutoIncrementPrimary(array $col): bool
    {
        $isAutoIncrement = (string) ($col['extra'] ?? '')
                |> strtolower(...)
                |> (fn (string $s): bool => str_contains($s, 'auto_increment'));

        return ($col['key'] ?? '') === 'PRI' && $isAutoIncrement;
    }
}