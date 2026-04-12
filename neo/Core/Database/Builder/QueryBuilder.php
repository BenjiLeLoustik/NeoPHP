<?php
declare(strict_types=1);

namespace Neo\Core\Database\Builder;

use Neo\Core\Database\DatabaseConnection;
use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\Error\Exception\FrameworkException;
use PDO;
use PDOException;

class QueryBuilder
{
    private string $table = '';
    private array $select = [];
    private array $where = [];
    private array $params = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $joins = [];
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = DatabaseConnection::getPdo();
    }

    public function table(string $table): self
    {
        $this->table = $this->sanitizeIdentifier($table);
        return $this;
    }

    private function sanitizeIdentifier(string $name): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new FrameworkException(
                title: 'Query Builder Error',
                message: "Identifiant SQL invalide : '{$name}'.",
                code: 500
            );
        }
        return $name;
    }

    private function sanitizeColumn(string $column): string
    {
        if ($column === '*') {
            return $column;
        }

        if (preg_match('/^[a-zA-Z0-9_]+\.\*$/', $column)) {
            return $column;
        }

        if (!preg_match('/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)?$/', $column)) {
            throw new FrameworkException(
                title: 'Query Builder Error',
                message: "Colonne SQL invalide : '{$column}'.",
                code: 500
            );
        }

        return $column;
    }

    private function sanitizeJoinOn(string $on): string
    {
        if (!preg_match('/^[a-zA-Z0-9_.=\s]+$/', $on)) {
            throw new FrameworkException(
                title: 'Query Builder Error',
                message: "Clause JOIN invalide : '{$on}'.",
                code: 500
            );
        }

        return $on;
    }

    private function sanitizeOperator(string $operator): string
    {
        $allowed = ['=', '!=', '<', '<=', '>', '>=', 'LIKE'];

        if (!in_array(strtoupper($operator), $allowed, true)) {
            throw new FrameworkException(
                title: 'Query Builder Error',
                message: "Opérateur SQL invalide : '{$operator}'.",
                code: 500
            );
        }

        return strtoupper($operator);
    }

    public function select(array $columns = ['*']): self
    {
        if ($columns !== ['*']) {
            foreach ($columns as &$col) {
                $col = $this->sanitizeColumn($col);
            }
        }

        $this->select = $columns;
        return $this;
    }

    private function makeParam(string $base): string
    {
        $base = preg_replace('/[^a-zA-Z0-9_]/', '_', $base);
        return $base . '_' . count($this->params);
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function where(string|array $column, string $operator, mixed $value): self
    {
        $operator = $this->sanitizeOperator($operator);
        $conditions = [];
        foreach ((array) $column as $col) {
            $col = $this->sanitizeColumn($col);

            foreach ((array) $value as $val) {
                $paramKey = $this->makeParam($col);
                $conditions[] = "$col $operator :$paramKey";
                $this->params[$paramKey] = $val;
            }
        }
        $this->where[] = ['AND', implode(' AND ', $conditions)];
        return $this;
    }

    public function andWhere(string|array $column, string $operator, mixed $value): self
    {
        return $this->where($column, $operator, $value);
    }

    public function orWhere(string|array $column, string $operator, mixed $value): self
    {
        $operator = $this->sanitizeOperator($operator);
        $conditions = [];
        foreach ((array) $column as $col) {
            $col = $this->sanitizeColumn($col);

            foreach ((array) $value as $val) {
                $paramKey = $this->makeParam($col);
                $conditions[] = "$col $operator :$paramKey";
                $this->params[$paramKey] = $val;
            }
        }
        $this->where[] = ['OR', implode(' OR ', $conditions)];
        return $this;
    }

    public function whereLike(string|array $column, mixed $value): self
    {
        $conditions = [];
        foreach ((array) $column as $col) {
            $col = $this->sanitizeColumn($col);

            foreach ((array) $value as $val) {
                $paramKey = $this->makeParam($col);
                $conditions[] = "$col LIKE :$paramKey";
                $this->params[$paramKey] = "%$val%";
            }
        }
        $this->where[] = ['AND', implode(' AND ', $conditions)];
        return $this;
    }

    public function orWhereLike(string|array $column, mixed $value): self
    {
        $conditions = [];
        foreach ((array) $column as $col) {
            $col = $this->sanitizeColumn($col);

            foreach ((array) $value as $val) {
                $paramKey = $this->makeParam($col);
                $conditions[] = "$col LIKE :$paramKey";
                $this->params[$paramKey] = "%$val%";
            }
        }
        $this->where[] = ['OR', implode(' OR ', $conditions)];
        return $this;
    }

    public function whereWord(string|array $column, mixed $word): self
    {
        $conditions = [];
        foreach ((array) $column as $col) {
            $col = $this->sanitizeColumn($col);

            foreach ((array) $word as $w) {
                $paramKey = $this->makeParam($col);
                $conditions[] = "CONCAT(' ', LOWER($col), ' ') LIKE :$paramKey";
                $this->params[$paramKey] = '% ' . mb_strtolower($w) . ' %';
            }
        }
        $this->where[] = ['AND', implode(' AND ', $conditions)];
        return $this;
    }

    public function orWhereWord(string|array $column, mixed $word): self
    {
        $conditions = [];
        foreach ((array) $column as $col) {
            $col = $this->sanitizeColumn($col);

            foreach ((array) $word as $w) {
                $paramKey = $this->makeParam($col);
                $conditions[] = "CONCAT(' ', LOWER($col), ' ') LIKE :$paramKey";
                $this->params[$paramKey] = '% ' . mb_strtolower($w) . ' %';
            }
        }
        $this->where[] = ['OR', implode(' OR ', $conditions)];
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $column = $this->sanitizeColumn($column);
        $placeholders = [];
        foreach ($values as $value) {
            $paramKey = $this->makeParam($column);
            $placeholders[] = ":$paramKey";
            $this->params[$paramKey] = $value;
        }
        $this->where[] = ['AND', "$column IN (" . implode(',', $placeholders) . ')'];
        return $this;
    }

    public function whereNull(string $column): self
    {
        $column = $this->sanitizeColumn($column);
        $this->where[] = ['AND', "$column IS NULL"];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $column = $this->sanitizeColumn($column);
        $this->where[] = ['AND', "$column IS NOT NULL"];
        return $this;
    }

    public function between(string $column, mixed $from, mixed $to): self
    {
        $column = $this->sanitizeColumn($column);
        $key1 = $this->makeParam($column . '_from');
        $key2 = $this->makeParam($column . '_to');
        $this->where[] = ['AND', "$column BETWEEN :$key1 AND :$key2"];
        $this->params[$key1] = $from;
        $this->params[$key2] = $to;
        return $this;
    }

    public function join(string $table, string $on): self
    {
        $table = $this->sanitizeIdentifier($table);
        $on = $this->sanitizeJoinOn($on);

        $this->joins[] = "JOIN $table ON $on";
        return $this;
    }

    public function leftJoin(string $table, string $on): self
    {
        $table = $this->sanitizeIdentifier($table);
        $on = $this->sanitizeJoinOn($on);

        $this->joins[] = "LEFT JOIN $table ON $on";
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new FrameworkException(
                title: 'Query Builder Error',
                message: "Direction ORDER BY invalide : '{$direction}'.",
                code: 500
            );
        }

        $column = $this->sanitizeColumn($column);
        $this->orderBy[] = "$column " . $direction;
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

    private function buildWhere(): string
    {
        if (empty($this->where)) {
            return '';
        }

        $sql   = ' WHERE ';
        $first = true;

        foreach ($this->where as [$logic, $condition]) {
            if ($first) {
                $sql  .= $condition;
                $first = false;
            } else {
                $sql .= " $logic $condition";
            }
        }

        return $sql;
    }

    private function buildSelect(): string
    {
        $cols = $this->select ? implode(', ', $this->select) : '*';
        $sql  = "SELECT $cols FROM {$this->table}";

        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->buildWhere();

        if ($this->orderBy) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
            if ($this->offset !== null) {
                $sql .= " OFFSET {$this->offset}";
            }
        }

        return $sql;
    }

    public function get(): array
    {
        try {
            $stmt = $this->pdo->prepare($this->toSql());
            $stmt->execute($this->params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new FrameworkException(
                title: 'Query Builder Error',
                message: "Erreur lors de l'exécution de la requête : " . $e->getMessage(),
                code: 500,
                previous: $e
            );
        }
    }

    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function firstModel(string $modelClass): ?AbstractModel
    {
        $row = $this->first();
        return $row ? new $modelClass($row) : null;
    }

    public function count(): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->table}";

            if ($this->joins) {
                $sql .= ' ' . implode(' ', $this->joins);
            }

            $sql .= $this->buildWhere();

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->params);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new FrameworkException(
                title: 'Query Builder Error',
                message: "Erreur lors du count : " . $e->getMessage(),
                code: 500,
                previous: $e
            );
        }
    }

    public function toSql(): string
    {
        return $this->buildSelect();
    }

    public function insert(array $data): bool
    {
        try {
            $columns      = array_keys($data);
            $placeholders = array_map(fn($c) => ":$c", $columns);

            $sql  = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->table,
                implode(',', $columns),
                implode(',', $placeholders)
            );

            $stmt = $this->pdo->prepare($sql);

            foreach ($data as $key => $value) {
                $value === null
                    ? $stmt->bindValue(":$key", null, PDO::PARAM_NULL)
                    : $stmt->bindValue(":$key", $value);
            }

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new FrameworkException(
                title: 'Query Builder Error',
                message: "Erreur lors de l'insertion : " . $e->getMessage(),
                code: 500,
                previous: $e
            );
        }
    }

    public function insertGetId(array $data): string
    {
        $this->insert($data);
        return $this->pdo->lastInsertId();
    }

    public function update(array $data): bool
    {
        if (!$this->where) {
            throw new FrameworkException(
                title: 'Query Builder Error',
                message: 'Les requêtes UPDATE nécessitent au moins une condition WHERE.',
                code: 500
            );
        }

        try {
            $setParts = [];
            $setParams = [];

            foreach ($data as $key => $value) {
                $paramKey          = 'set_' . $key;
                $setParts[]        = "$key = :$paramKey";
                $setParams[$paramKey] = $value;
            }

            $sql  = "UPDATE {$this->table} SET " . implode(', ', $setParts) . $this->buildWhere();
            $stmt = $this->pdo->prepare($sql);

            foreach ($setParams as $key => $value) {
                $value === null
                    ? $stmt->bindValue(":$key", null, PDO::PARAM_NULL)
                    : $stmt->bindValue(":$key", $value);
            }

            foreach ($this->params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new FrameworkException(
                title: 'Query Builder Error',
                message: "Erreur lors de la mise à jour : " . $e->getMessage(),
                code: 500,
                previous: $e
            );
        }
    }

    public function delete(): bool
    {
        if (!$this->where) {
            throw new FrameworkException(
                title: 'Query Builder Error',
                message: 'Les requêtes DELETE nécessitent au moins une condition WHERE.',
                code: 500
            );
        }

        try {
            $sql = "DELETE FROM {$this->table} " . $this->buildWhere();
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($this->params);
        } catch (PDOException $e) {
            throw new FrameworkException(
                title: 'Query Builder Error',
                message: "Erreur lors de la suppression : " . $e->getMessage(),
                code: 500,
                previous: $e
            );
        }
    }

    public function paginate(int $perPage = 15, ?int $page = null): PaginationBuilder
    {
        $page = max(1, $page ?? (int) ($_GET['page'] ?? 1));

        $total = $this->count();

        $items = $this
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        return new PaginationBuilder(
            items:       $items,
            total:       $total,
            perPage:     $perPage,
            currentPage: $page,
        );
    }

    public function reset(): self
    {
        $this->table   = '';
        $this->select  = [];
        $this->where   = [];
        $this->params  = [];
        $this->orderBy = [];
        $this->limit   = null;
        $this->offset  = null;
        $this->joins   = [];
        return $this;
    }

    public function getModels(string $modelClass): array
    {
        $rows = $this->get();
        return array_map(fn($row) => new $modelClass($row), $rows);
    }

    public function beginTransaction(): void
    {
        try {
            $this->pdo->beginTransaction();
        } catch (\PDOException $e) {
            throw new FrameworkException(
                title: 'Transaction Error',
                message: 'Impossible de démarrer la transaction : ' . $e->getMessage(),
                code: 500,
                previous: $e
            );
        }
    }

    public function commit(): void
    {
        try {
            $this->pdo->commit();
        } catch (\PDOException $e) {
            throw new FrameworkException(
                title: 'Transaction Error',
                message: 'Impossible de committer la transaction : ' . $e->getMessage(),
                code: 500,
                previous: $e
            );
        }
    }

    public function rollback(): void
    {
        try {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } catch (\PDOException $e) {
            throw new FrameworkException(
                title: 'Transaction Error',
                message: 'Impossible de rollback la transaction : ' . $e->getMessage(),
                code: 500,
                previous: $e
            );
        }
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();

            if ($e instanceof FrameworkException) {
                throw $e;
            }

            throw new FrameworkException(
                title: 'Transaction Error',
                message: 'La transaction a échoué : ' . $e->getMessage(),
                code: 500,
                previous: $e
            );
        }
    }
}