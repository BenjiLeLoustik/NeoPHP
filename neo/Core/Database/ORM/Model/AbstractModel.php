<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Model;

use Neo\Core\Database\DatabaseConnection;
use Neo\Core\Database\ORM\Attribute\HasOne;
use Neo\Core\Database\ORM\Attribute\HasMany;
use Neo\Core\Database\ORM\Attribute\BelongsTo;
use Neo\Core\Database\ORM\Attribute\BelongsToMany;
use Neo\Core\Error\Exception\FrameworkException;
use PDO;
use PDOException;

abstract class AbstractModel
{
    protected static ?string $table = null;
    protected static string $primaryKey = 'id';
    protected array $data = [];
    protected array $relationsCache = [];
    private static array $instanceCache = [];
    protected bool $withTrashedRelations = false;
    protected bool $onlyTrashedRelations = false;
    protected array $fillable = [];
    protected bool $trackIdentity = true;
    protected bool $timestamps = true;
    protected bool $isLoadingRelations = false;
    private static array $loadingGuard = [];
    protected array $hidden = [];

    public function __construct(array $data = [], bool $autoLoadRelations = false, array $relationsToLoad = [])
    {
        $ref = new \ReflectionObject($this);

        foreach ($ref->getProperties() as $prop) {
            if ($prop->isStatic() || in_array($prop->getName(), $this->getInternalProperties())) {
                continue;
            }

            $name  = $prop->getName();
            $value = array_key_exists($name, $data) ? $data[$name]
                : ($prop->isInitialized($this) ? $this->$name : null);

            $value = $this->castValue($prop, $value);

            if (!$this->hasRelation($name)) {
                if (!$prop->isInitialized($this) || array_key_exists($name, $data)) {
                    $this->$name = $value;
                }
                $this->data[$name] = $this->$name;
            }
        }

        $this->registerIdentity();

        if ($autoLoadRelations) {
            $this->loadAllRelations();
        }

        foreach ($relationsToLoad as $rel) {
            $this->$rel;
        }
    }

    public function getRelationsCache(): array
    {
        return $this->relationsCache;
    }

    public static function clearIdentityMap(): void
    {
        self::$instanceCache = [];
    }

    private function getInternalProperties(): array
    {
        return [
            'data',
            'relationsCache',
            'withTrashedRelations',
            'onlyTrashedRelations',
            'fillable',
            'trackIdentity',
            'timestamps',
            'isLoadingRelations',
            'hidden',
        ];
    }

    /* ==========================================================
     | Identity Map
     * ========================================================== */

    public function registerIdentity(): void
    {
        if (!$this->trackIdentity) return;

        $pk = static::getPrimaryKey();
        $id = $this->$pk ?? null;

        if ($id === null || $id === 0 || $id === '0' || $id === '') return;

        $key = static::class . ':' . $id;
        self::$instanceCache[$key] = $this;
    }

    public static function getIdentity(int|string $id): ?self
    {
        return self::$instanceCache[static::class . ':' . $id] ?? null;
    }

    public static function removeIdentity(int|string $id): void
    {
        unset(self::$instanceCache[static::class . ':' . $id]);
    }

    /* ==========================================================
     | Magic access
     * ========================================================== */

    public function __get(string $name): mixed
    {
        if ($this->hasRelation($name)) {
            if (!array_key_exists($name, $this->relationsCache)) {
                $this->relationsCache[$name] = $this->loadRelation(
                    $name,
                    $this->withTrashedRelations,
                    $this->onlyTrashedRelations
                );
            }
            return $this->relationsCache[$name];
        }

        return $this->data[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        if (in_array($name, ['data', 'relationsCache'])) return;

        try {
            $prop = new \ReflectionProperty($this, $name);
            $prop->setAccessible(true);
            $value = $this->castValue($prop, $value);
            $prop->setValue($this, $value);
        } catch (\ReflectionException) {}

        $this->data[$name] = $value;
    }

    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    /* ==========================================================
     | Table / PK
     * ========================================================== */

    public static function getTable(): string
    {
        return static::$table
            ?? strtolower((new \ReflectionClass(static::class))->getShortName()) . 's';
    }

    public static function getPrimaryKey(): string
    {
        return static::$primaryKey;
    }

    /* ==========================================================
     | Relations metadata
     * ========================================================== */

    public function getRelations(): array
    {
        static $cache = [];
        $class = static::class;

        if (isset($cache[$class])) return $cache[$class];

        $ref = new \ReflectionClass($class);
        $relations = [];

        foreach ($ref->getProperties() as $prop) {
            $prop->setAccessible(true);

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

    /* ==========================================================
     | Soft delete helpers
     * ========================================================== */

    protected function filterTrashed(array $items, bool $include, bool $only): array
    {
        return array_values(array_filter($items, function ($item) use ($include, $only) {
            if (!$item instanceof self) return true;
            if ($only)        return $item->deleted_at !== null;
            if (!$include)    return $item->deleted_at === null;
            return true;
        }));
    }

    /* ==========================================================
     | Relation loader
     * ========================================================== */

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

    public function loadRelation(
        string $name,
        bool $includeTrashed = false,
        bool $onlyTrashed = false,
        bool $forceReload = false
    ): mixed {
        if (!$forceReload && array_key_exists($name, $this->relationsCache)) {
            return $this->relationsCache[$name];
        }

        $pk       = static::getPrimaryKey();
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
            $pdo      = DatabaseConnection::getPdo();
            $where    = $onlyTrashed
                ? 'deleted_at IS NOT NULL'
                : ($includeTrashed ? '1=1' : 'deleted_at IS NULL');

            try {
                if ($relation instanceof BelongsTo) {
                    $fk = $this->{$relation->foreignKey};
                    if (!$fk) {
                        $this->relationsCache[$name] = null;
                        return null;
                    }

                    $stmt = $pdo->prepare("SELECT * FROM {$relation->target::getTable()} WHERE {$relation->target::getPrimaryKey()} = ? AND $where LIMIT 1");
                    $stmt->execute([$fk]);
                    $row    = $stmt->fetch(PDO::FETCH_ASSOC);
                    $result = $row ? $relation->target::hydrateRow($row) : null;

                    $this->relationsCache[$name] = $result;
                    return $result;
                }

                if ($relation instanceof HasOne) {
                    $stmt = $pdo->prepare("SELECT * FROM {$relation->target::getTable()} WHERE {$relation->foreignKey} = ? AND $where LIMIT 1");
                    $stmt->execute([$this->{$relation->localKey}]);
                    $row    = $stmt->fetch(PDO::FETCH_ASSOC);
                    $result = $row ? $relation->target::hydrateRow($row) : null;

                    $this->relationsCache[$name] = $result;
                    return $result;
                }

                if ($relation instanceof HasMany) {
                    $stmt = $pdo->prepare("SELECT * FROM {$relation->target::getTable()} WHERE {$relation->foreignKey} = ? AND $where");
                    $stmt->execute([$this->{$relation->localKey}]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    $objs = array_map(fn($r) => $relation->target::hydrateRow($r), $rows);

                    $this->relationsCache[$name] = $objs;
                    return $objs;
                }

                if ($relation instanceof BelongsToMany) {
                    $sql = "
                        SELECT t.* FROM {$relation->target::getTable()} t
                        INNER JOIN {$relation->pivotTable} p
                            ON p.{$relation->pivotTargetKey} = t.{$relation->target::getPrimaryKey()}
                        WHERE p.{$relation->pivotLocalKey} = ?
                          AND " . ($onlyTrashed
                            ? 't.deleted_at IS NOT NULL'
                            : ($includeTrashed ? '1=1' : 't.deleted_at IS NULL'));

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$this->{$relation->localKey}]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    $objs = array_map(fn($r) => $relation->target::hydrateRow($r), $rows);

                    $this->relationsCache[$name] = $objs;
                    return $objs;
                }
            } catch (PDOException $e) {
                throw new FrameworkException(
                    title: 'Relation Load Error',
                    message: "Erreur lors du chargement de la relation '{$name}' sur " . static::class . ' : ' . $e->getMessage(),
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

    public static function hydrateRow(array $row): static
    {
        return new static($row);
    }

    /* ==========================================================
     | Relation loading helpers
     * ========================================================== */

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

    public function loadAllRelations(
        bool $recursive = true,
        array &$loaded = [],
        int $depth = 0,
        int $maxDepth = 5,
    ): void {
        $pk  = static::getPrimaryKey();
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

            if ($rel instanceof self) {
                $rel->loadAllRelations(true, $loaded, $depth + 1, $maxDepth);
            } elseif (is_array($rel)) {
                foreach ($rel as $obj) {
                    if ($obj instanceof self) {
                        $obj->loadAllRelations(true, $loaded, $depth + 1, $maxDepth);
                    }
                }
            }
        }
    }

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

    public function withTrashedRelations(): self
    {
        $this->withTrashedRelations = true;
        $this->onlyTrashedRelations = false;
        return $this;
    }

    public function onlyTrashedRelations(): self
    {
        $this->withTrashedRelations = true;
        $this->onlyTrashedRelations = true;
        return $this;
    }

    /* ==========================================================
     | Serialization
     * ========================================================== */

    public function toArray(
        array $stack = [],
        int $depth = 0,
        int $maxDepth = 3,
        bool $includeRelations = true,
        bool $autoLoadMissing = false
    ): array {
        $pk    = static::getPrimaryKey();
        $objId = spl_object_hash($this);

        if (isset($stack[$objId]) || $depth >= $maxDepth) {
            return ['id' => $this->data[$pk] ?? null, '_circular' => true];
        }

        $stack[$objId] = true;
        $result        = [];

        foreach ($this->data as $name => $value) {
            if (in_array($name, $this->getInternalProperties(), true)) continue;
            if (in_array($name, $this->hidden ?? [], true)) continue;
            $result[$name] = $value instanceof \DateTime ? $value->format('Y-m-d H:i:s') : $value;
        }

        if ($includeRelations) {
            foreach ($this->getRelations() as $name => $_) {
                if (!$autoLoadMissing && !array_key_exists($name, $this->relationsCache)) continue;

                $rel = $this->relationsCache[$name] ?? null;

                if ($rel instanceof self) {
                    $result[$name] = $rel->toArray($stack, $depth + 1, $maxDepth, $includeRelations, $autoLoadMissing);
                } elseif (is_array($rel)) {
                    $result[$name] = array_map(
                        fn($m) => $m instanceof AbstractModel
                            ? $m->toArray($stack, $depth + 1, $maxDepth, $includeRelations, $autoLoadMissing)
                            : $m,
                        $rel
                    );
                }
            }
        }

        return $result;
    }

    public function toModel(array &$loaded = [], int $depth = 0, int $maxDepth = 5): self
    {
        $pk  = static::getPrimaryKey();
        $key = static::class . ':' . ($this->$pk ?? spl_object_id($this));

        if (isset($loaded[$key]) || $depth >= $maxDepth) return $this;

        $loaded[$key] = true;

        foreach ($this->relationsCache as $name => $rel) {
            if ($rel instanceof self) {
                $this->$name = $rel->toModel($loaded, $depth + 1, $maxDepth);
            } elseif (is_array($rel)) {
                $this->$name = array_map(
                    fn($m) => $m instanceof self ? $m->toModel($loaded, $depth + 1, $maxDepth) : $m,
                    $rel
                );
            }
        }

        return $this;
    }

    /* ==========================================================
     | Persistence
     * ========================================================== */

    public function fill(array $data): void
    {
        $relations = array_keys($this->getRelations());

        foreach ($data as $key => $value) {
            if (!empty($this->fillable) && !in_array($key, $this->fillable, true)) continue;
            if (in_array($key, $relations, true)) continue;

            if (property_exists($this, $key)) {
                $prop = new \ReflectionProperty($this, $key);
                $prop->setAccessible(true);
                $prop->setValue($this, $value);
            }

            $this->data[$key] = $value;
        }
    }

    public function save(): bool
    {
        try {
            $pdo   = DatabaseConnection::getPdo();
            $data  = $this->toDatabase();
            $pk    = static::getPrimaryKey();
            $table = static::getTable();

            foreach ($this->getRelations() as $relName => $_) {
                unset($data[$relName]);
            }

            static $columnsCache = [];
            if (!isset($columnsCache[$table])) {
                $stmt = $pdo->query("DESCRIBE $table");
                if ($stmt === false) {
                    throw new FrameworkException(
                        title: 'Model Save Error',
                        message: "Impossible de décrire la table '{$table}'.",
                        code: 500
                    );
                }
                $columnsCache[$table] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }

            $tableColumns = $columnsCache[$table];
            $isInsert     = empty($this->$pk);
            $now          = (new \DateTime())->format('Y-m-d H:i:s');

            if ($isInsert) {
                if (in_array('created_at', $tableColumns) && !isset($data['created_at'])) $data['created_at'] = $now;
                if (in_array('updated_at', $tableColumns) && !isset($data['updated_at'])) $data['updated_at'] = $now;
            } else {
                if (in_array('updated_at', $tableColumns)) $data['updated_at'] = $now;
            }

            if ($isInsert) {
                unset($data[$pk]);
                $columns      = implode(',', array_keys($data));
                $placeholders = implode(',', array_fill(0, count($data), '?'));
                $stmt         = $pdo->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
                $stmt->execute(array_values($data));
                $this->$pk = (int) $pdo->lastInsertId();
            } else {
                $set  = implode(', ', array_map(fn($col) => "$col = ?", array_keys($data)));
                $stmt = $pdo->prepare("UPDATE $table SET $set WHERE $pk = ?");
                $stmt->execute([...array_values($data), $this->$pk]);
            }

            $this->registerIdentity();
            return true;

        } catch (FrameworkException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new FrameworkException(
                title: 'Model Save Error',
                message: "Erreur lors de la sauvegarde du modèle '" . static::class . "' : " . $e->getMessage(),
                code: 500,
                previous: $e
            );
        }
    }

    public function saveRelation(string $relationName, array $entries): void
    {
        $relations = $this->getRelations();

        if (!isset($relations[$relationName])) {
            throw new FrameworkException(
                title: 'Model Relation Error',
                message: "La relation '{$relationName}' n'existe pas sur " . static::class . '.',
                code: 500
            );
        }

        $relation = $relations[$relationName];

        if (!$relation instanceof HasMany) {
            throw new FrameworkException(
                title: 'Model Relation Error',
                message: "saveRelation() ne supporte que les relations HasMany. '{$relationName}' n'est pas HasMany.",
                code: 500
            );
        }

        $pdo = DatabaseConnection::getPdo();
        $target = $relation->target;
        $foreignKey = $relation->foreignKey;
        $localKey = $relation->localKey;
        $targetPk = $target::getPrimaryKey();
        $table = $target::getTable();
        $parentId = $this->$localKey;

        $submittedIds = [];
        foreach ($entries as $entry) {
            $pk = is_object($entry)
                ? $entry->$targetPk
                : ($entry[$targetPk] ?? null);

            if ($pk) $submittedIds[] = (int)$pk;
        }

        $stmt = $pdo->prepare(
            "SELECT {$targetPk} FROM {$table} WHERE {$foreignKey} = ? AND deleted_at IS NULL"
        );

        $stmt->execute([$parentId]);
        $existingIds = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), $targetPk);
        $existingIds = array_map('intval', $existingIds);

        $toDelete = array_diff($existingIds, $submittedIds);

        if (!empty($toDelete)) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
            $now = date('Y-m-d H:i:s');

            $hasSoftDelete = $this->targetHasSoftDelete($target, $pdo);

            if ($hasSoftDelete) {
                $stmt = $pdo->prepare(
                    "UPDATE {$table} SET deleted_at = ? WHERE {$targetPk} IN ({$placeholders})"
                );
                $stmt->execute([$now, ...$toDelete]);
            } else {
                $stmt = $pdo->prepare(
                    "DELETE FROM {$table} WHERE {$targetPk} IN ({$placeholders})"
                );
                $stmt->execute([...$toDelete]);
            }

            foreach ($toDelete as $deletedId) {
                $target::removeIdentity($deletedId);
            }
        }

        $now = date('Y-m-d H:i:s');

        foreach ($entries as $entry) {
            if (!$entry instanceof AbstractModel) {
                $entry = new $target(is_array($entry) ? $entry : []);
            }

            $entry->$foreignKey = $parentId;

            $pk = $entry->$targetPk ?? null;

            if ($pk) {
                $data = $entry->toDatabase();
                unset(
                    $data[$targetPk],
                    $data['created_at'],
                    $data['deleted_at']
                );

                $data['updated_at'] = $now;

                $setParts = array_map(fn($col) => "{$col} = ?", array_keys($data));
                $stmt     = $pdo->prepare(
                    "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$targetPk} = ?"
                );
                $stmt->execute([...array_values($data), $pk]);

                $entry->registerIdentity();
            } else {
                $data = $entry->toDatabase();
                unset($data[$targetPk]);

                $data['created_at'] ??= $now;
                $data['updated_at'] ??= $now;

                $columns      = implode(', ', array_keys($data));
                $placeholders = implode(', ', array_fill(0, count($data), '?'));

                $stmt = $pdo->prepare(
                    "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})"
                );
                $stmt->execute(array_values($data));

                $entry->$targetPk = (int)$pdo->lastInsertId();
                $entry->registerIdentity();
            }
        }

        unset($this->relationsCache[$relationName]);
    }

    private function targetHasSoftDelete(string $target, \PDO $pdo): bool
    {
        static $cache = [];

        if (isset($cache[$target])) {
            return $cache[$target];
        }

        $table = $target::getTable();
        $stmt  = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE 'deleted_at'");
        $stmt->execute();

        return $cache[$target] = (bool)$stmt->fetch();
    }

    public function toDatabase(): array
    {
        $data      = [];
        $ref       = new \ReflectionObject($this);
        $relations = array_keys($this->getRelations());

        foreach ($ref->getProperties() as $prop) {
            if ($prop->isStatic()) continue;

            $name = $prop->getName();
            if (in_array($name, $this->getInternalProperties(), true)) continue;
            if (in_array($name, $relations, true)) continue;

            $prop->setAccessible(true);
            $value = $prop->getValue($this);

            if ($value instanceof self) continue;
            if (is_array($value) && !empty($value) && $value[0] instanceof self) continue;

            $data[$name] = $value instanceof \DateTime
                ? $value->format('Y-m-d H:i:s')
                : $value;
        }

        return $data;
    }

    /* ==========================================================
     | Casting
     * ========================================================== */

    protected function castValue(\ReflectionProperty $prop, mixed $value): mixed
    {
        $type = $prop->getType();
        if (!$type instanceof \ReflectionNamedType) return $value;

        $name     = $type->getName();
        $nullable = $type->allowsNull();

        if ($name === \DateTime::class) {
            if ($value instanceof \DateTime) return $value;
            if ($value === null || $value === '') return $nullable ? null : new \DateTime();
            try {
                return new \DateTime((string) $value);
            } catch (\Exception) {
                return $nullable ? null : new \DateTime();
            }
        }

        if ($value === null) {
            return $nullable ? null : match ($name) {
                'int'    => 0,
                'float'  => 0.0,
                'bool'   => false,
                'string' => '',
                'array'  => [],
                default  => null,
            };
        }

        return match ($name) {
            'int'    => (int) $value,
            'float'  => (float) $value,
            'bool'   => (bool) $value,
            'string' => (string) $value,
            default  => $value,
        };
    }

    protected static function buildSoftDeleteWhere(
        bool $includeTrashed,
        bool $onlyTrashed,
        string $column = 'deleted_at'
    ): string {
        return $onlyTrashed
            ? "$column IS NOT NULL"
            : ($includeTrashed ? '1=1' : "$column IS NULL");
    }

    public static function eagerLoad(
        array $models,
        string $relation,
        bool $includeTrashed = false,
        bool $onlyTrashed = false
    ): void {
        if (empty($models)) return;

        $first     = $models[0];
        $relations = $first->getRelations();

        if (!isset($relations[$relation])) return;

        $rel   = $relations[$relation];
        $pdo   = DatabaseConnection::getPdo();
        $where = self::buildSoftDeleteWhere($includeTrashed, $onlyTrashed);

        $syncWithIdentityMap = function (self $obj): self {
            return $obj;
        };

        try {
            if ($rel instanceof HasMany || $rel instanceof HasOne) {
                $localKey   = $rel->localKey;
                $foreignKey = $rel->foreignKey;
                $target     = $rel->target;

                $ids = array_values(array_unique(array_filter(
                    array_map(fn($m) => $m->$localKey, $models)
                )));

                if (!$ids) return;

                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt         = $pdo->prepare("SELECT * FROM {$target::getTable()} WHERE $foreignKey IN ($placeholders) AND $where");
                $stmt->execute($ids);
                $rows    = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $grouped = [];

                foreach ($rows as $row) {
                    $obj                          = $syncWithIdentityMap($target::hydrateRow($row));
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
                $target     = $rel->target;
                $targetPk   = $target::getPrimaryKey();

                $ids = array_values(array_unique(array_filter(
                    array_map(fn($m) => $m->$foreignKey, $models)
                )));

                if (!$ids) return;

                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt         = $pdo->prepare("SELECT * FROM {$target::getTable()} WHERE $targetPk IN ($placeholders) AND $where");
                $stmt->execute($ids);
                $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $mapped = [];

                foreach ($rows as $row) {
                    $obj              = $syncWithIdentityMap($target::hydrateRow($row));
                    $mapped[$row[$targetPk]] = $obj;
                }

                foreach ($models as $model) {
                    $model->relationsCache[$relation] = $mapped[$model->$foreignKey] ?? null;
                }

                return;
            }

            if ($rel instanceof BelongsToMany) {
                $localKey       = $rel->localKey;
                $target         = $rel->target;
                $pivotTable     = $rel->pivotTable;
                $pivotLocalKey  = $rel->pivotLocalKey;
                $pivotTargetKey = $rel->pivotTargetKey;
                $targetPk       = $target::getPrimaryKey();

                $ids = array_values(array_unique(array_filter(
                    array_map(fn($m) => $m->$localKey, $models)
                )));

                if (!$ids) return;

                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql          = "
                    SELECT t.*, p.$pivotLocalKey AS pivot_local
                    FROM {$target::getTable()} t
                    INNER JOIN $pivotTable p ON p.$pivotTargetKey = t.$targetPk
                    WHERE p.$pivotLocalKey IN ($placeholders)
                    AND $where
                ";

                $stmt    = $pdo->prepare($sql);
                $stmt->execute($ids);
                $rows    = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $grouped = [];

                foreach ($rows as $row) {
                    $obj                           = $syncWithIdentityMap($target::hydrateRow($row));
                    $grouped[$row['pivot_local']][] = $obj;
                }

                foreach ($models as $model) {
                    $model->relationsCache[$relation] = $grouped[$model->$localKey] ?? [];
                }

                return;
            }

        } catch (PDOException $e) {
            throw new FrameworkException(
                title: 'Eager Load Error',
                message: "Erreur lors du chargement eager de la relation '{$relation}' : " . $e->getMessage(),
                code: 500,
                previous: $e
            );
        }

        foreach ($models as $model) {
            $model->loadRelation($relation, $includeTrashed, $onlyTrashed);
        }
    }
}