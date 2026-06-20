<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Model\Concerns;

use Neo\Core\Database\DatabaseConnection;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\Attribute\HasMany;
use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\Error\Exception\FrameworkException;
use PDO;

trait HasPersistence
{
    /** @var array<string, AbstractModel> */
    private static array $instanceCache = [];

    protected bool $trackIdentity = true;

    protected bool $timestamps = true;

    /** @var array<int, string> */
    protected array $fillable = [];

    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    public function registerIdentity(): void
    {
        if (!$this->trackIdentity) return;

        $pk = static::getPrimaryKey();
        $id = $this->$pk ?? null;

        if ($id === null || $id === 0 || $id === '0' || $id === '') return;

        $key = static::class . ':' . $id;
        self::$instanceCache[$key] = $this;
    }

    /**
     * @return static|null
     */
    public static function getIdentity(int|string $id): ?self
    {
        $key = static::class . ':' . $id;
        if (!isset(self::$instanceCache[$key])) {
            return null;
        }

        /** @var static */
        return self::$instanceCache[$key];
    }

    public static function removeIdentity(int|string $id): void
    {
        unset(self::$instanceCache[static::class . ':' . $id]);
    }

    public static function clearIdentityMap(): void
    {
        self::$instanceCache = [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fill(array $data): void
    {
        $relations = array_keys($this->getRelations());

        foreach ($data as $key => $value) {
            if (!empty($this->fillable) && !in_array($key, $this->fillable, true)) continue;
            if (in_array($key, $relations, true)) continue;

            if (property_exists($this, $key)) {
                $prop = new \ReflectionProperty($this, $key);
                $prop->setValue($this, $value);
            }

            $this->data[$key] = $value;
        }
    }

    public function save(): bool
    {
        try {
            $pdo = DatabaseConnection::getPdo();
            $data = $this->toDatabase();
            $pk = static::getPrimaryKey();
            $table = static::getTable();

            foreach ($this->getRelations() as $relName => $_) {
                unset($data[$relName]);
            }

            static $columnsCache = [];
            if (!isset($columnsCache[$table])) {
                $stmt = $pdo->query("DESCRIBE `$table`");
                if ($stmt === false) {
                    throw new DatabaseException(
                        title: 'Model Save Error',
                        message: sprintf("Unable to describe table '%s'.", $table),
                        code: 500
                    );
                }
                $columnsCache[$table] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }

            $tableColumns = $columnsCache[$table];
            $isInsert = empty($this->$pk);
            $now = (new \DateTime())->format('Y-m-d H:i:s');

            if ($isInsert) {
                if (in_array('created_at', $tableColumns) && !isset($data['created_at'])) {
                    $data['created_at'] = $now;
                }
                if (in_array('updated_at', $tableColumns) && !isset($data['updated_at'])) {
                    $data['updated_at'] = $now;
                }
            } else {
                if (in_array('updated_at', $tableColumns)) {
                    $data['updated_at'] = $now;
                }
            }

            if ($isInsert) {
                unset($data[$pk]);
                $columns = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
                $placeholders = implode(', ', array_fill(0, count($data), '?'));
                $stmt = $pdo->prepare("INSERT INTO `$table` ($columns) VALUES ($placeholders)");
                $stmt->execute(array_values($data));
                $this->$pk = (int) $pdo->lastInsertId();
            } else {
                $set = implode(', ', array_map(fn($col) => "`$col` = ?", array_keys($data)));
                $stmt = $pdo->prepare("UPDATE `$table` SET $set WHERE `$pk` = ?");
                $stmt->execute([...array_values($data), $this->$pk]);
            }

            $this->registerIdentity();
            return true;

        } catch (FrameworkException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new DatabaseException(
                title: 'Model Save Error',
                message: sprintf("Error while saving model '%s': %s", static::class, $e->getMessage()),
                code: 500,
                previous: $e
            );
        }
    }

    /**
     * @param array<int, AbstractModel|array<string, mixed>> $entries
     * @throws DatabaseException
     */
    public function saveRelation(string $relationName, array $entries): void
    {
        $relations = $this->getRelations();

        if (!isset($relations[$relationName])) {
            throw new DatabaseException(
                title: 'Model Relation Error',
                message: sprintf("Relation '%s' does not exist on %s.", $relationName, static::class),
                code: 500
            );
        }

        $relation = $relations[$relationName];

        if (!$relation instanceof HasMany) {
            throw new DatabaseException(
                title: 'Model Relation Error',
                message: sprintf("saveRelation() only supports HasMany relations. '%s' is not HasMany.", $relationName),
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

            if ($pk) $submittedIds[] = (int) $pk;
        }

        $stmt = $pdo->prepare(
            "SELECT `{$targetPk}` FROM `{$table}` WHERE `{$foreignKey}` = ? AND deleted_at IS NULL"
        );

        $stmt->execute([$parentId]);
        $existingIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), $targetPk);
        $existingIds = array_map('intval', $existingIds);

        $toDelete = array_diff($existingIds, $submittedIds);

        if (!empty($toDelete)) {
            $placeholders = implode(', ', array_fill(0, count($toDelete), '?'));
            $now = date('Y-m-d H:i:s');

            $hasSoftDelete = $this->targetHasSoftDelete($target, $pdo);

            if ($hasSoftDelete) {
                $stmt = $pdo->prepare(
                    "UPDATE `{$table}` SET deleted_at = ? WHERE `{$targetPk}` IN ({$placeholders})"
                );
                $stmt->execute([$now, ...$toDelete]);
            } else {
                $stmt = $pdo->prepare(
                    "DELETE FROM `{$table}` WHERE `{$targetPk}` IN ({$placeholders})"
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

                $setParts = array_map(fn($col) => "`{$col}` = ?", array_keys($data));
                $stmt = $pdo->prepare(
                    "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE `{$targetPk}` = ?"
                );
                $stmt->execute([...array_values($data), $pk]);

                $entry->registerIdentity();
            } else {
                $data = $entry->toDatabase();
                unset($data[$targetPk]);

                $data['created_at'] ??= $now;
                $data['updated_at'] ??= $now;

                $columns = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
                $placeholders = implode(', ', array_fill(0, count($data), '?'));

                $stmt = $pdo->prepare(
                    "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})"
                );
                $stmt->execute(array_values($data));

                $entry->$targetPk = (int) $pdo->lastInsertId();
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
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE 'deleted_at'");
        $stmt->execute();

        return $cache[$target] = (bool) $stmt->fetch();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(): array
    {
        $data = [];
        $ref = new \ReflectionObject($this);
        $relations = array_keys($this->getRelations());

        foreach ($ref->getProperties() as $prop) {
            if ($prop->isStatic()) continue;

            $name = $prop->getName();
            if (in_array($name, $this->getInternalProperties(), true)) continue;
            if (in_array($name, $relations, true)) continue;

            $value = $prop->getValue($this);

            if ($value instanceof AbstractModel) continue;
            if (is_array($value) && !empty($value) && $value[0] instanceof AbstractModel) continue;

            $data[$name] = $value instanceof \DateTime
                ? $value->format('Y-m-d H:i:s')
                : $value;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $row
     * @throws DatabaseException
     */
    public static function hydrateRow(array $row): static
    {
        return new static($row);
    }
}