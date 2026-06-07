<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Repository;

use Neo\Core\Database\Builder\PaginationBuilder;
use Neo\Core\Database\DatabaseConnection;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\Database\Builder\QueryBuilder;
use PDO;
use PDOException;
use PDOStatement;

abstract class AbstractRepository
{
    protected string $modelClass;
    protected string $table;
    protected string $primaryKey;
    protected PDO $pdo;
    protected QueryBuilder $builder;
    protected array $lastResult = [];
    protected array $with = [];
    protected bool $softDelete = true;
    protected bool $includeTrashed = false;
    protected bool $onlyTrashed = false;

    public function __construct(?string $modelClass = null)
    {
        if ($modelClass !== null) {
            $this->modelClass = $modelClass;
        }

        if (!isset($this->modelClass)) {
            throw new DatabaseException(
                title: 'Repository Configuration Error',
                message: 'Repository must define $modelClass.',
                code: 500
            );
        }

        $this->pdo = DatabaseConnection::getPdo();
        $this->table = $this->modelClass::getTable();
        $this->primaryKey = $this->modelClass::getPrimaryKey();
        $this->builder = (new QueryBuilder())->table($this->table);
    }

    protected function resetState(): void
    {
        $this->includeTrashed = false;
        $this->onlyTrashed = false;
        $this->with = [];
        $this->builder = (new QueryBuilder())->table($this->table);
    }

    public function with(string|array $relations): self
    {
        foreach ((array)$relations as $relation) {
            $parts = explode('.', $relation);
            $root = array_shift($parts);

            $this->with[$root]['nested'][] = $parts;
            $this->with[$root]['withTrashed'] ??= false;
            $this->with[$root]['onlyTrashed'] ??= false;
        }

        return $this;
    }

    public function withTrashed(string $relation): self
    {
        $this->with($relation);
        $this->with[$relation]['withTrashed'] = true;
        $this->with[$relation]['onlyTrashed'] = false;

        return $this;
    }

    public function onlyTrashed(string $relation): self
    {
        $this->with($relation);
        $this->with[$relation]['withTrashed'] = true;
        $this->with[$relation]['onlyTrashed'] = true;

        return $this;
    }

    public function withTrashedModels(): self
    {
        $this->includeTrashed = true;
        $this->onlyTrashed = false;
        return $this;
    }

    public function onlyTrashedModels(): self
    {
        $this->includeTrashed = true;
        $this->onlyTrashed = true;
        return $this;
    }

    public function restore(int|string $id): bool
    {
        if (!$this->softDelete) {
            return false;
        }

        $restored = $this->builder
            ->reset()
            ->table($this->table)
            ->where($this->primaryKey, '=', $id)
            ->whereNotNull('deleted_at')
            ->update(['deleted_at' => null]);

        if ($restored) {
            $this->modelClass::removeIdentity($id);
        }

        return $restored;
    }

    public function forceDelete(int|string $id): bool
    {
        $success = $this->builder
            ->reset()
            ->table($this->table)
            ->where($this->primaryKey, '=', $id)
            ->delete();

        if ($success) {
            $this->modelClass::removeIdentity($id);
        }

        return $success;
    }

    protected function applySoftDelete(QueryBuilder $qb): void
    {
        if (!$this->softDelete) {
            return;
        }

        $col = $this->table . '.deleted_at';

        if ($this->onlyTrashed) {
            $qb->whereNotNull($col);
        } elseif (!$this->includeTrashed) {
            $qb->whereNull($col);
        }
    }

    public function qb(): QueryBuilder
    {
        $qb = (new QueryBuilder())->table($this->table);

        if ($this->softDelete) {
            $col = $this->table . '.deleted_at';

            if ($this->onlyTrashed) {
                $qb->whereNotNull($col);
            } elseif (!$this->includeTrashed) {
                $qb->whereNull($col);
            }
        }

        return $qb;
    }

    public function queryBuilder(): QueryBuilder
    {
        return $this->qb();
    }

    public function find(int|string $id): ?AbstractModel
    {
        $model = $this->modelClass::getIdentity($id);

        if (!$model) {
            $rows = $this->qb()
                ->where($this->primaryKey, '=', $id)
                ->limit(1)
                ->get();

            if (!$rows) {
                return null;
            }

            $model = $this->hydrateSingle($rows[0]);
        }

        $model = $this->eagerLoadRelations($model);

        if ($this->includeTrashed || $this->onlyTrashed) {
            $model->withTrashedRelations();
        }

        $this->resetState();
        return $model;
    }

    public function findAll(?int $limit = null, ?int $offset = null): static
    {
        $qb = $this->qb();
        if ($limit !== null) $qb->limit($limit);
        if ($offset !== null) $qb->offset($offset);

        $this->builder = $qb;

        $rows = $qb->get();
        $this->hydrateMany($rows);

        if (!empty($this->with)) {
            $this->eagerLoadTree($this->lastResult, $this->buildWithTree());
        }

        if ($this->includeTrashed || $this->onlyTrashed) {
            foreach ($this->lastResult as $model) {
                $model->withTrashedRelations();
            }
        }

        $this->resetState();

        return $this;
    }

    public function findBy(
        string $column,
        mixed $value,
        bool $single = true,
        ?int $limit = null
    ): AbstractModel|null|static {
        $qb = $this->qb()->where($column, '=', $value);

        if ($limit !== null) {
            $qb->limit($limit);
        }

        $this->builder = $qb;

        if ($single) {
            $rows = $qb->limit(1)->get();
            if (!$rows) return null;

            $model = $this->hydrateSingle($rows[0]);

            if ($this->includeTrashed || $this->onlyTrashed) {
                $model->withTrashedRelations();
            }

            $model = $this->eagerLoadRelations($model);
            $this->resetState();
            return $model;
        }

        $rows = $qb->get();
        $this->hydrateMany($rows);

        if (!empty($this->with)) {
            $this->eagerLoadTree($this->lastResult, $this->buildWithTree());
        }

        if ($this->includeTrashed || $this->onlyTrashed) {
            foreach ($this->lastResult as $m) {
                $m->withTrashedRelations();
            }
        }

        return $this;
    }

    public function create(AbstractModel $model): AbstractModel
    {
        $data = $model->toDatabase();

        foreach ($model->getRelations() as $relName => $_) {
            unset($data[$relName]);
        }

        unset($data[$this->primaryKey], $data['created_at'], $data['updated_at']);

        if ($model->usesTimestamps()) {
            $data = $this->ensureTimestamps($data, true);
        }

        $id = $this->qb()->insertGetId($data);

        $model->{$this->primaryKey} = (int) $id;
        $model->registerIdentity();

        return $model;
    }

    public function update(int|string $id, AbstractModel $model): bool
    {
        $data = $model->toDatabase();

        if ($model->usesTimestamps()) {
            $data = $this->ensureTimestamps($data, false);
        }

        foreach ($model->getRelations() as $relName => $_) {
            unset($data[$relName]);
        }

        unset($data[$this->primaryKey], $data['created_at'], $data['deleted_at']);

        return $this->qb()->where($this->primaryKey, '=', $id)->update($data);
    }

    public function delete(int|string|AbstractModel $target): bool
    {
        $id = $target instanceof AbstractModel
            ? $target->{$this->primaryKey}
            : $target;

        if ($this->softDelete) {
            return $this->qb()
                ->where($this->primaryKey, '=', $id)
                ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }

        return $this->qb()
            ->where($this->primaryKey, '=', $id)
            ->delete();
    }

    private function ensureTimestamps(array $data, bool $isCreate = false, bool $isDelete = false): array
    {
        $now = date('Y-m-d H:i:s');

        if ($isCreate) {
            $data['created_at'] ??= $now;
            $data['updated_at'] ??= $now;
        } else {
            $data['updated_at'] = $now;
        }

        if ($isDelete) {
            $data['deleted_at'] = $now;
        }

        return $data;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);

            if ($stmt === false) {
                throw new DatabaseException(
                    title: 'Repository Query Error',
                    message: sprintf("Unable to prepare the query: %s", $sql),
                    code: 500
                );
            }

            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new DatabaseException(
                title: 'Repository Query Error',
                message: sprintf("Error while executing the query: %s", $e->getMessage()),
                code: 500,
                previous: $e
            );
        }
    }

    private function buildWithTree(): array
    {
        $tree = [];
        foreach ($this->with as $relation => $options) {
            $tree[$relation] ??= [];

            foreach ($options['nested'] as $chain) {
                $current = &$tree[$relation];
                foreach ($chain as $part) {

                    if(!isset($current[$part])) {
                        $current[$part] = [];
                    }

                    $current = &$current[$part];
                }
            }
        }

        return $tree;
    }

    private function eagerLoadTree(array $models, array $tree): void
    {
        foreach ($tree as $relation => $nested) {
            $options = $this->with[$relation] ?? [];

            AbstractModel::eagerLoad(
                $models,
                $relation,
                $options['withTrashed'] ?? false,
                $options['onlyTrashed'] ?? false
            );

            $nextModels = [];
            foreach ($models as $model) {
                $rel = $model->relation($relation);

                if ($rel instanceof AbstractModel) {
                    $nextModels[] = $rel;
                } elseif (is_array($rel)) {
                    foreach ($rel as $r) {
                        if ($r instanceof AbstractModel) {
                            $nextModels[] = $r;
                        }
                    }
                }
            }

            if (!empty($nested) && !empty($nextModels)) {
                $this->eagerLoadTree($nextModels, $nested);
            }
        }
    }

    protected function eagerLoadRelations(AbstractModel $model): AbstractModel
    {
        if (empty($this->with)) {
            return $model;
        }

        foreach ($this->with as $relation => $options) {
            if (!array_key_exists($relation, $model->getRelationsCache())) {
                $model->loadRelation(
                    $relation,
                    $options['withTrashed'] ?? false,
                    $options['onlyTrashed'] ?? false,
                    true
                );
            }
        }

        foreach ($this->with as $relation => $options) {
            if (!empty($options['nested'])) {
                $rel = $model->getRelationsCache()[$relation] ?? null;
                foreach ($options['nested'] as $chain) {
                    $this->loadNestedRelations($rel, $chain);
                }
            }
        }

        return $model;
    }

    private function loadNestedRelations(mixed $rel, array $chain): void
    {
        if (empty($chain)) {
            return;
        }

        $next = array_shift($chain);

        if ($rel instanceof AbstractModel) {
            $cached = $rel->getRelationsCache()[$next] ?? '__not_set__';
            if ($cached === '__not_set__' || $cached === []) {
                $rel->loadRelation($next, false, false, true);
            }
            $this->loadNestedRelations($rel->getRelationsCache()[$next] ?? null, $chain);
        } elseif (is_array($rel)) {
            foreach ($rel as $item) {
                if ($item instanceof AbstractModel) {
                    $chainCopy = $chain;
                    $cached = $item->getRelationsCache()[$next] ?? '__not_set__';
                    if ($cached === '__not_set__' || $cached === []) {
                        $item->loadRelation($next, false, false, true);
                    }
                    $this->loadNestedRelations($item->getRelationsCache()[$next] ?? null, $chainCopy);
                }
            }
        }
    }

    public function getModels(): array
    {
        return $this->lastResult;
    }

    public function getModel(): ?AbstractModel
    {
        return $this->lastResult[0] ?? null;
    }

    public function toArray(): array
    {
        return array_map(
            fn(AbstractModel $m) => $m->toArray(), $this->lastResult ?? []
        );
    }

    public function toList(): array
    {
        return $this->toArray();
    }

    public function paginate(int $perPage = 15, ?int $page = null): PaginationBuilder
    {
        $page = max(1, $page ?? (int)($_GET['page'] ?? 1));
        $total = (clone $this->builder)->count();

        $rows = $this->builder
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        $this->hydrateMany($rows);

        if (!empty($this->with)) {
            $this->eagerLoadTree($this->lastResult, $this->buildWithTree());
        }

        return new PaginationBuilder(
            items: array_map(fn($model) => $model->toArray(), $this->lastResult),
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    protected function hydrateSingle(array $row): AbstractModel
    {
        $model = new $this->modelClass($row);
        $model->registerIdentity();
        return $model;
    }

    protected function hydrateMany(array $rows): void
    {
        $this->lastResult = array_map(
            fn($r) => $this->modelClass::hydrateRow($r),
            $rows
        );
    }

    protected function hydrateJoined(array $rows, string $relationName): void
    {
        $targetClass = $this->modelClass::getRelationTarget($relationName);

        $this->lastResult = array_map(function ($row) use ($relationName, $targetClass) {
            $model = new $this->modelClass($row);
            $relData = [];

            foreach ($row as $key => $value) {
                if (str_starts_with($key, $relationName . '_')) {
                    $relData[str_replace($relationName . '_', '', $key)] = $value;
                }
            }

            if (!empty($relData)) {
                $model->$relationName = [new $targetClass($relData)];
            }

            return $model;
        }, $rows);
    }
}