<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Database\Connection\DatabaseConnection;
use Neo\Core\Validator\Abstract\AbstractConstraint;
use ReflectionClass;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Unique extends AbstractConstraint
{
    private ?string $table;

    private ?string $column;

    /** @var array<string, mixed> */
    private array $conditions;

    private ?int $excludedId;

    /**
     * @param array<string, mixed> $conditions
     */
    public function __construct(
        string $message = '',
        ?string $table = null,
        ?string $column = null,
        array $conditions = [],
        ?int $excludedId = null
    ) {
        $this->table = $table;
        $this->column = $column;
        $this->conditions = $conditions;
        $this->excludedId = $excludedId;
        parent::__construct($message);
    }

    public function validate(mixed $value, ?object $object = null): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!$object) {
            throw new \LogicException('Unique constraint requires object instance.');
        }

        $column = $this->column ?? $this->resolvedPropertyName
            ?? throw new \LogicException('Unable to resolve column name for Unique constraint.');

        $table = $this->resolveTable($object);

        $pdo = DatabaseConnection::getPdo();

        $sql = sprintf(
            "SELECT COUNT(*) FROM %s WHERE %s = ?",
            $this->escapeIdentifier($table),
            $this->escapeIdentifier($column)
        );

        $params = [$value];

        $excludedId = $this->excludedId ?? (property_exists($object, 'id') && $object->id !== null ? $object->id : null);

        if ($excludedId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludedId;
        }

        foreach ($this->conditions as $col => $val) {
            if ($val === null) {
                $sql .= sprintf(" AND %s IS NULL", $this->escapeIdentifier($col));
            } else {
                $sql .= sprintf(" AND %s = ?", $this->escapeIdentifier($col));
                $params[] = $val;
            }
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() === 0;
    }

    private function resolveTable(object $object): string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        $refClass = new ReflectionClass($object);

        if ($refClass->hasProperty('table')) {
            $prop = $refClass->getProperty('table');
            return $prop->getValue($object);
        }

        throw new \LogicException('Unable to resolve table name for Unique constraint.');
    }

    private function escapeIdentifier(string $name): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \LogicException("Invalid identifier: {$name}");
        }
        return "`{$name}`";
    }
}