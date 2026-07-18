<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Model\Concerns;

use Neo\Core\Database\DatabaseConnection;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\Attribute\BelongsTo;
use Neo\Core\Database\ORM\Attribute\BelongsToMany;
use Neo\Core\Database\ORM\Attribute\HasMany;
use Neo\Core\Database\ORM\Attribute\HasOne;
use Neo\Core\Database\ORM\Model\AbstractModel;
use PDO;
use PDOException;

trait HasRelationships
{
    /** @var array<string, mixed> */
    protected array $relationsCache = [];

    protected bool $withTrashedRelations = false;

    protected bool $onlyTrashedRelations = false;

    /** @var array<int, string> */
    protected static array $loadingGuard = [];

    /**
     * @return array<string, mixed>
     */
    public function getRelationsCache(): array
    {
        return $this->relationsCache;
    }

    public function setRelation(string $name, mixed $value): static
    {
        $relations = $this->getRelations();

        if (isset($relations[$name]) && $relations[$name] instanceof BelongsTo) {
            $foreignKey = $relations[$name]->foreignKey;
            $ownerKey = $relations[$name]->ownerKey;

            $this->setAttribute($foreignKey, $value?->$ownerKey);
        }

        $this->relationsCache[$name] = $value;

        return $this;
    }

    /**
     * @return array<string, object>
     */
    public function getRelations(): array
    {
        static $cache = [];
        $class = static::class;

        if (isset($cache[$class])) return $cache[$class];

        $ref = new \ReflectionClass($class);
        $relations = [];

        foreach ($ref->getProperties() as $prop) {
            $attrs = [
                ...$prop->getAttributes(HasOne::class),
                ...$prop->getAttributes(HasMany::class),
                ...$prop->getAttributes(BelongsTo::class),
                ...$prop->getAttributes(BelongsToMany::class),
            ];

            foreach ($attrs as $attr) {
                $relations[$prop->getName()] = $attr->newInstance();
            }
        }

        return $cache[$class] = $relations;
    }

    protected function hasRelation(string $name): bool
    {
        return isset($this->getRelations()[$name]);
    }

    /**
     * @throws \ReflectionException
     */
    public static function getRelationTarget(string $relationName): ?string
    {
        $ref  = new \ReflectionClass(static::class);
        $prop = $ref->getProperty($relationName);

        $attrs = [
            ...$prop->getAttributes(HasOne::class),
            ...$prop->getAttributes(HasMany::class),
            ...$prop->getAttributes(BelongsTo::class),
            ...$prop->getAttributes(BelongsToMany::class),
        ];

        return !empty($attrs) ? $attrs[0]->newInstance()->target : null;
    }

    /**
     * @param array<int, AbstractModel> $items
     * @return array<int, AbstractModel>
     */
    protected function filterTrashed(array $items, bool $include, bool $only): array
    {
        return array_values(array_filter($items, function ($item) use ($include, $only) {
            if (!$item instanceof AbstractModel) {
                return true;
            }

            /** @phpstan-ignore-next-line */
            if ($only) {
                return $item->deleted_at !== null;
            }

            /** @phpstan-ignore-next-line */
            if (!$include) {
                return $item->deleted_at === null;
            }

            return true;
        }));
    }

    /**
     * @throws DatabaseException
     */
    public function loadRelation(
        string $name,
        bool $includeTrashed = false,
        bool $onlyTrashed = false,
        bool $forceReload = false
    ): mixed {
        if (!$forceReload && array_key_exists($name, $this->relationsCache)) {
            return $this->relationsCache[$name];
        }

        $pk = static::getPrimaryKey();
        $guardKey = static::class . ':' . ($this->$pk ?? spl_object_id($this)) . ':' . $name;

        if (!$forceReload && isset(self::$loadingGuard[$guardKey])) {
            return null;
        }

        self::$loadingGuard[$guardKey] = true;

        try {
            $relations = $this->getRelations();
            if (!isset($relations[$name])) {
                $this->relationsCache[$name] = null;
                return null;
            }

            $relation = $relations[$name];
            $pdo = DatabaseConnection::getPdo();

            try {
                if ($relation instanceof BelongsTo) {
                    $fk = $this->{$relation->foreignKey};
                    if (!$fk) {
                        $this->relationsCache[$name] = null;
                        return null;
                    }

                    $where = self::buildRelationSoftDeleteWhere(
                        $relation->target::getTable(), $pdo, $includeTrashed, $onlyTrashed
                    );

                    $sql = <<<SQL
SELECT * FROM `%s` WHERE `%s` = ? AND %s LIMIT 1
SQL;

                    $stmt = $pdo->prepare(
                        sprintf(
                            $sql,
                            $relation->target::getTable(),
                            $relation->ownerKey,
                            $where
                        )
                    );

                    $stmt->execute([$fk]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $result = $row ? $relation->target::hydrateRow($row) : null;

                    $this->relationsCache[$name] = $result;
                    return $result;
                }

                if ($relation instanceof HasOne) {
                    $where = self::buildRelationSoftDeleteWhere(
                        $relation->target::getTable(), $pdo, $includeTrashed, $onlyTrashed
                    );

                    $sql = <<<SQL
SELECT * FROM `%s` WHERE `%s` = ? AND %s LIMIT 1
SQL;

                    $stmt = $pdo->prepare(
                        sprintf(
                            $sql,
                            $relation->target::getTable(),
                            $relation->foreignKey,
                            $where
                        )
                    );
                    $stmt->execute([$this->{$relation->localKey}]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $result = $row ? $relation->target::hydrateRow($row) : null;

                    $this->relationsCache[$name] = $result;
                    return $result;
                }

                if ($relation instanceof HasMany) {
                    $where = self::buildRelationSoftDeleteWhere(
                        $relation->target::getTable(), $pdo, $includeTrashed, $onlyTrashed
                    );

                    $sql = <<<SQL
SELECT * FROM `%s` WHERE `%s` = ? AND %s
SQL;

                    $stmt = $pdo->prepare(
                        sprintf(
                            $sql,
                            $relation->target::getTable(),
                            $relation->foreignKey,
                            $where
                        )
                    );
                    $stmt->execute([$this->{$relation->localKey}]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    $objs = array_map(fn($r) => $relation->target::hydrateRow($r), $rows);

                    $this->relationsCache[$name] = $objs;
                    return $objs;
                }

                if ($relation instanceof BelongsToMany) {
                    $where = self::buildRelationSoftDeleteWhere(
                        $relation->target::getTable(), $pdo, $includeTrashed, $onlyTrashed, 't'
                    );

                    $sql = <<<SQL
SELECT t.* FROM `%s` t
INNER JOIN `%s` p
ON p.`%s` = t.`%s`
WHERE p.`%s` = ?
AND %s
SQL;

                    $stmt = $pdo->prepare(
                        sprintf(
                            $sql,
                            $relation->target::getTable(),
                            $relation->pivotTable,
                            $relation->pivotTargetKey,
                            $relation->target::getPrimaryKey(),
                            $relation->pivotLocalKey,
                            $where
                        )
                    );
                    $stmt->execute([$this->{$relation->localKey}]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    $objs = array_map(fn($r) => $relation->target::hydrateRow($r), $rows);

                    $this->relationsCache[$name] = $objs;
                    return $objs;
                }
            } catch (PDOException $e) {
                throw new DatabaseException(
                    title: 'Relation Load Error',
                    message: sprintf("Error while loading relation '%s' on %s: %s", $name, static::class, $e->getMessage()),
                    code: 500,
                    previous: $e
                );
            }

            $this->relationsCache[$name] = null;
            return null;

        } finally {
            unset(self::$loadingGuard[$guardKey]);
        }
    }

    /**
     * @param array<int, string> $relations
     * @throws DatabaseException
     */
    public function loadRelations(array $relations): void
    {
        foreach ($relations as $name) {
            if ($this->hasRelation($name)) {
                $this->relationsCache[$name] = $this->loadRelation(
                    $name,
                    $this->withTrashedRelations,
                    $this->onlyTrashedRelations
                );
            }
        }
    }

    /**
     * @param array<string, bool> $loaded
     * @throws DatabaseException
     */
    public function loadAllRelations(
        bool $recursive = true,
        array &$loaded = [],
        int $depth = 0,
        int $maxDepth = 5,
    ): void {
        $pk = static::getPrimaryKey();
        $key = static::class . ':' . ($this->$pk ?? spl_object_id($this));

        if (isset($loaded[$key]) || $depth >= $maxDepth) return;

        $loaded[$key] = true;

        foreach ($this->getRelations() as $name => $_) {
            $this->relationsCache[$name] = $this->loadRelation(
                $name,
                $this->withTrashedRelations,
                $this->onlyTrashedRelations
            );

            if (!$recursive) continue;

            $rel = $this->relationsCache[$name];

            if ($rel instanceof AbstractModel) {
                $rel->loadAllRelations(true, $loaded, $depth + 1, $maxDepth);
            } elseif (is_array($rel)) {
                foreach ($rel as $obj) {
                    if ($obj instanceof AbstractModel) {
                        $obj->loadAllRelations(true, $loaded, $depth + 1, $maxDepth);
                    }
                }
            }
        }
    }

    /**
     * @throws DatabaseException
     */
    public function relation(string $name): mixed
    {
        if (!$this->hasRelation($name)) return null;

        if (!array_key_exists($name, $this->relationsCache)) {
            $this->relationsCache[$name] = $this->loadRelation(
                $name,
                $this->withTrashedRelations,
                $this->onlyTrashedRelations
            );
        }

        return $this->relationsCache[$name];
    }

    public function withTrashedRelations(): static
    {
        $this->withTrashedRelations = true;
        $this->onlyTrashedRelations = false;
        return $this;
    }

    public function onlyTrashedRelations(): static
    {
        $this->withTrashedRelations = true;
        $this->onlyTrashedRelations = true;
        return $this;
    }

    /**
     * @param array<int, AbstractModel> $models
     * @throws DatabaseException
     */
    public static function eagerLoad(
        array $models,
        string $relation,
        bool $includeTrashed = false,
        bool $onlyTrashed = false
    ): void {
        if (empty($models)) return;

        $first = $models[0];
        $relations = $first->getRelations();

        if (!isset($relations[$relation])) return;

        $rel = $relations[$relation];
        $pdo = DatabaseConnection::getPdo();

        $syncWithIdentityMap = function (AbstractModel $obj): AbstractModel {
            return $obj;
        };

        try {
            if ($rel instanceof HasMany || $rel instanceof HasOne) {
                $localKey = $rel->localKey;
                $target = $rel->target;
                $foreignKey = $rel->foreignKey;

                $ids = array_values(array_unique(array_filter(
                    array_map(fn($m) => $m->$localKey, $models)
                )));

                if (!$ids) return;

                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                $where = self::buildRelationSoftDeleteWhere(
                    $target::getTable(), $pdo, $includeTrashed, $onlyTrashed
                );

                $sql = <<<SQL
SELECT * FROM `%s` WHERE `%s` IN (%s) AND %s
SQL;


                $stmt = $pdo->prepare(
                    sprintf(
                        $sql,
                        $target::getTable(),
                        $foreignKey,
                        $placeholders,
                        $where
                    )
                );
                $stmt->execute($ids);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $grouped = [];

                foreach ($rows as $row) {
                    $obj = $syncWithIdentityMap($target::hydrateRow($row));
                    $grouped[$row[$foreignKey]][] = $obj;
                }

                foreach ($models as $model) {
                    $key = $model->$localKey;
                    $model->relationsCache[$relation] = $rel instanceof HasOne
                        ? ($grouped[$key][0] ?? null)
                        : ($grouped[$key] ?? []);
                }

                return;
            }

            if ($rel instanceof BelongsTo) {
                $foreignKey = $rel->foreignKey;
                $target = $rel->target;
                $targetPk = $rel->ownerKey;

                $ids = array_values(array_unique(array_filter(
                    array_map(fn($m) => $m->$foreignKey, $models)
                )));

                if (!$ids) return;

                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                $where = self::buildRelationSoftDeleteWhere(
                    $target::getTable(), $pdo, $includeTrashed, $onlyTrashed
                );

                $sql = <<<SQL
SELECT * FROM `%s` WHERE `%s` IN (%s) AND %s
SQL;


                $stmt = $pdo->prepare(
                    sprintf(
                        $sql,
                        $target::getTable(),
                        $targetPk,
                        $placeholders,
                        $where
                    )
                );
                $stmt->execute($ids);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $mapped = [];

                foreach ($rows as $row) {
                    $obj = $syncWithIdentityMap($target::hydrateRow($row));
                    $mapped[$row[$targetPk]] = $obj;
                }

                foreach ($models as $model) {
                    $model->relationsCache[$relation] = $mapped[$model->$foreignKey] ?? null;
                }

                return;
            }

            if ($rel instanceof BelongsToMany) {
                $localKey = $rel->localKey;
                $target = $rel->target;
                $pivotTable = $rel->pivotTable;
                $pivotLocalKey = $rel->pivotLocalKey;
                $pivotTargetKey = $rel->pivotTargetKey;
                $targetPk = $target::getPrimaryKey();

                $ids = array_values(array_unique(array_filter(
                    array_map(fn($m) => $m->$localKey, $models)
                )));

                if (!$ids) return;

                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                $where = self::buildRelationSoftDeleteWhere(
                    $target::getTable(), $pdo, $includeTrashed, $onlyTrashed, 't'
                );

                $sql = <<<SQL
SELECT t.*, p.`%s` AS pivot_local
FROM `%s` t
INNER JOIN `%s` p ON p.`%s` = t.`%s`
WHERE p.`%s` IN (%s)
AND %s
SQL;

                $stmt = $pdo->prepare(
                    sprintf(
                        $sql,
                        $pivotLocalKey,
                        $target::getTable(),
                        $pivotTable,
                        $pivotTargetKey,
                        $targetPk,
                        $pivotLocalKey,
                        $placeholders,
                        $where
                    )
                );
                $stmt->execute($ids);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $grouped = [];

                foreach ($rows as $row) {
                    $obj = $syncWithIdentityMap($target::hydrateRow($row));
                    $grouped[$row['pivot_local']][] = $obj;
                }

                foreach ($models as $model) {
                    $model->relationsCache[$relation] = $grouped[$model->$localKey] ?? [];
                }

                return;
            }

        } catch (PDOException $e) {
            throw new DatabaseException(
                title: 'Eager Load Error',
                message: sprintf("Error while eager loading relation '%s': %s", $relation, $e->getMessage()),
                code: 500,
                previous: $e
            );
        }

        foreach ($models as $model) {
            $model->loadRelation($relation, $includeTrashed, $onlyTrashed);
        }
    }

    private static function tableHasColumn(string $table, string $column, PDO $pdo): bool
    {
        static $cache = [];

        $key = $table . ':' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $escaped = str_replace(['%', '_'], ['\%', '\_'], $column);

        $sql = <<<SQL
SHOW COLUMNS FROM `%s` LIKE '%s'
SQL;


        $stmt = $pdo->query(
            sprintf(
                $sql,
                $table,
                $escaped,
            )
        );

        return $cache[$key] = $stmt !== false && (bool) $stmt->fetch();
    }

    private static function buildRelationSoftDeleteWhere(
        string $table,
        PDO $pdo,
        bool $includeTrashed,
        bool $onlyTrashed,
        string $alias = ''
    ): string {
        $prefix = $alias !== '' ? "{$alias}." : '';

        if (!self::tableHasColumn($table, 'deleted_at', $pdo)) {
            return '1=1';
        }

        return $onlyTrashed
            ? "{$prefix}deleted_at IS NOT NULL"
            : ($includeTrashed ? '1=1' : "{$prefix}deleted_at IS NULL");
    }
}