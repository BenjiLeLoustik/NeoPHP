<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Query;

use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\Pagination\Paginator;

final class QueryBuilder
{
    private string $table = '';

    /** @var list<string> */
    private array $columns = ['*'];

    /** @var list<array{table: string, first: string, operator: string, second: string, type: string}> */
    private array $joins = [];

    /** @var list<array<string, mixed>> */
    private array $wheres = [];

    /** @var list<mixed> */
    private array $bindings = [];

    /** @var list<string> */
    private array $groups = [];

    /** @var list<array{column: string, operator: string}> */
    private array $havings = [];

    /** @var list<array{column: string, direction: string}> */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public static function for(DatabaseManager $db, string $table): self
    {
        return new self($db)->table($table);
    }

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function select(string ...$columns): self
    {
        $this->columns = $columns === [] ? ['*'] : $columns;
        return $this;
    }

    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
        string $type = 'INNER'
    ): self {
        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function where(string $column, string $operator, mixed $value, string $boolean = 'AND'): self
    {
        $this->wheres[] = ['type' => 'basic', 'column' => $column, 'operator' => $operator, 'boolean' => $boolean];
        $this->bindings[] = $value;
        return $this;
    }

    public function andWhere(string $column, string $operator, mixed $value): self
    {
        return $this->where($column, $operator, $value, 'AND');
    }

    public function orWhere(string $column, string $operator, mixed $value): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = ['type' => 'null', 'column' => $column, 'boolean' => $boolean];
        return $this;
    }

    /**
     * @param list<mixed> $values
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        if ($values === []) {
            $this->wheres[] = ['type' => 'raw', 'sql' => '1 = 0', 'boolean' => $boolean];
            return $this;
        }
        $this->wheres[] = ['type' => 'in', 'column' => $column, 'count' => count($values), 'boolean' => $boolean];
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }
        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->groups[] = $column;
        }
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): self
    {
        $this->havings[] = compact('column', 'operator');
        $this->bindings[] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = ['column' => $column, 'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC'];
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        return $this->db->fetchAll($this->toSql(), $this->bindings);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $this->limit = 1;
        return $this->db->fetch($this->toSql(), $this->bindings);
    }

    public function value(string $column): mixed
    {
        $row = $this->select($column)->first();
        return $row[$this->stripAlias($column)] ?? null;
    }

    public function count(string $column = '*'): int
    {
        $this->columns = ["COUNT($column) AS aggregate"];
        $row = $this->db->fetch($this->toSql(), $this->bindings);
        return (int) ($row['aggregate'] ?? 0);
    }

    /**
     * @return Paginator<array<string, mixed>>
     */
    public function paginate(int $page = 1, int $perPage = 15): Paginator
    {
        $page = max(1, $page);

        $totalItems = (clone $this)->count();

        $offset = ($page - 1) * $perPage;
        $items = $this->limit($perPage)->offset($offset)->get();

        return new Paginator($items, $totalItems, $page, $perPage);
    }

    /**
     * @param array<string, mixed> $data
     * @throws DatabaseException
     */
    public function insert(array $data): bool
    {
        $columns = array_keys($data)
                |> (fn (array $k): array => array_map($this->quote(...), $k))
                |> (fn (array $k): string => implode(', ', $k));

        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->quote($this->table), $columns, $placeholders);

        return $this->db->execute($sql, array_values($data));
    }

    /**
     * @param array<string, mixed> $data
     * @throws DatabaseException
     */
    public function insertGetId(array $data): string
    {
        $this->insert($data);
        return $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @throws DatabaseException
     */
    public function update(array $data): bool
    {
        $sets = [];
        $values = [];
        foreach ($data as $column => $value) {
            $sets[] = $this->quote($column) . ' = ?';
            $values[] = $value;
        }
        [$whereSql, $whereBindings] = $this->compileWheres();
        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->quote($this->table),
            implode(', ', $sets),
            $whereSql
        );
        return $this->db->execute($sql, [...$values, ...$whereBindings]);
    }

    /**
     * @throws DatabaseException
     */
    public function delete(): bool
    {
        [$whereSql, $whereBindings] = $this->compileWheres();
        $sql = sprintf('DELETE FROM %s%s', $this->quote($this->table), $whereSql);
        return $this->db->execute($sql, $whereBindings);
    }

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->quote($this->table);

        foreach ($this->joins as $join) {
            $sql .= sprintf(
                ' %s JOIN %s ON %s %s %s',
                $join['type'],
                $this->quote($join['table']),
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }

        [$whereSql] = $this->compileWheres();
        $sql .= $whereSql;

        if ($this->groups !== []) {
            $sql .= ' GROUP BY ' . (
                $this->groups
                    |> (fn (array $g): array => array_map($this->quote(...), $g))
                    |> (fn (array $g): string => implode(', ', $g))
                );
        }

        if ($this->havings !== []) {
            $parts = array_map(fn(array $h) => $this->quote($h['column']) . ' ' . $h['operator'] . ' ?', $this->havings);
            $sql .= ' HAVING ' . implode(' AND ', $parts);
        }

        if ($this->orders !== []) {
            $parts = array_map(fn(array $o) => $this->quote($o['column']) . ' ' . $o['direction'], $this->orders);
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
            if ($this->offset !== null) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }

        return $sql;
    }

    /**
     * @return array{string, list<mixed>}
     * @throws DatabaseException
     */
    private function compileWheres(): array
    {
        if ($this->wheres === []) {
            return ['', $this->bindings];
        }

        $sql = '';
        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : ' ' . $where['boolean'] . ' ';
            $sql .= $boolean . match ($where['type']) {
                    'basic' => $this->quote($where['column']) . ' ' . $where['operator'] . ' ?',
                    'null' => $this->quote($where['column']) . ' IS NULL',
                    'in' => $this->quote($where['column']) . ' IN (' . implode(', ', array_fill(0, $where['count'], '?')) . ')',
                    'raw' => $where['sql'],
                    default => throw new DatabaseException(
                        title: 'Query Builder Error',
                        message: sprintf('Unknown where clause type: %s', is_scalar($where['type']) ? (string) $where['type'] : gettype($where['type'])),
                        code: 500
                    ),
                };
        }

        return [' WHERE ' . $sql, $this->bindings];
    }

    private function quote(string $identifier): string
    {
        if (str_contains($identifier, '.')) {
            return explode('.', $identifier)
                    |> (fn($x) => array_map($this->quote(...), $x))
                    |> (fn($x) => implode('.', $x));
        }
        if ($identifier === '*') {
            return '*';
        }
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function stripAlias(string $column): ?string
    {
        $parts = explode('.', $column);
        return array_last($parts);
    }
}