<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Model;

use DateTime;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\Model\Concerns\HasPersistence;
use Neo\Core\Database\ORM\Model\Concerns\HasRelationships;

/**
 * @property DateTime|string|null $deleted_at
 */
abstract class AbstractModel
{
    use HasPersistence;
    use HasRelationships;

    protected static ?string $table = null;

    protected static ?string $connection = null;

    protected static string $primaryKey = 'id';

    /** @var array<string, mixed> */
    protected array $data = [];

    protected bool $isLoadingRelations = false;

    /** @var array<int, string> */
    protected array $hidden = [];

    /**
     * @param array<string, mixed> $data
     * @param bool $autoLoadRelations
     * @param array<int, string> $relationsToLoad
     * @throws DatabaseException
     */
    public function __construct(array $data = [], bool $autoLoadRelations = false, array $relationsToLoad = [])
    {
        $ref = new \ReflectionObject($this);

        foreach ($ref->getProperties() as $prop) {
            if ($prop->isStatic() || in_array($prop->getName(), $this->getInternalProperties())) {
                continue;
            }

            $name = $prop->getName();
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
            /** @phpstan-ignore expr.resultUnused */
            $this->$rel;
        }
    }

    /**
     * @return array<int, string>
     */
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

    /**
     * @throws DatabaseException
     */
    public function __get(string $name): mixed
    {
        if ($this->hasRelation($name)) {
            return $this->relation($name);
        }

        return $this->data[$name] ?? null;
    }

    /**
     * @throws DatabaseException
     */
    protected function getRelationValue(string $name): mixed
    {
        if (!array_key_exists($name, $this->relationsCache)) {
            $this->relationsCache[$name] = $this->loadRelation(
                $name,
                $this->withTrashedRelations,
                $this->onlyTrashedRelations
            );
        }

        return $this->relationsCache[$name];
    }

    public function __set(string $name, mixed $value): void
    {
        if (in_array($name, ['data', 'relationsCache'])) {
            return;
        }

        $this->setAttribute($name, $value);
    }

    public static function getConnection(): ?string
    {
        return static::$connection;
    }

    protected function setAttribute(string $name, mixed $value): static
    {
        try {
            $prop = new \ReflectionProperty($this, $name);
            $value = $this->castValue($prop, $value);
        } catch (\ReflectionException) {}

        $this->$name = $value;
        $this->data[$name] = $value;

        return $this;
    }

    public static function getTable(): string
    {
        return static::$table
            ?? strtolower(new \ReflectionClass(static::class)->getShortName()) . 's';
    }

    public static function getPrimaryKey(): string
    {
        return static::$primaryKey;
    }

    /**
     * @param array<string, mixed> $stack
     * @return array<string, mixed>
     */
    public function toArray(
        array $stack = [],
        int $depth = 0,
        int $maxDepth = 3,
        bool $includeRelations = true,
        bool $autoLoadMissing = false
    ): array {
        $pk = static::getPrimaryKey();
        $objId = spl_object_hash($this);

        if (isset($stack[$objId]) || $depth >= $maxDepth) {
            return ['id' => $this->data[$pk] ?? null, '_circular' => true];
        }

        $stack[$objId] = true;
        $result = [];

        foreach ($this->data as $name => $value) {
            if (in_array($name, $this->getInternalProperties(), true)) continue;
            if (in_array($name, $this->hidden, true)) continue;
            $result[$name] = $value instanceof DateTime ? $value->format('Y-m-d H:i:s') : $value;
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

    /**
     * @param array<string, bool> $loaded
     */
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

    protected function castValue(\ReflectionProperty $prop, mixed $value): mixed
    {
        $type = $prop->getType();
        if (!$type instanceof \ReflectionNamedType) return $value;

        $name = $type->getName();
        $nullable = $type->allowsNull();

        if ($name === DateTime::class) {
            if ($value instanceof DateTime) return $value;
            if ($value === null || $value === '') return $nullable ? null : new DateTime();
            try {
                return new DateTime((string) $value);
            } catch (\Exception) {
                return $nullable ? null : new DateTime();
            }
        }

        if ($value === null) {
            return $nullable ? null : match ($name) {
                'int' => 0,
                'float' => 0.0,
                'bool' => false,
                'string' => '',
                'array' => [],
                default => null,
            };
        }

        return match ($name) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            default => $value,
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
}