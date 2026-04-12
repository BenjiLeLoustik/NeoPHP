<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Constraint;
use Neo\Core\Database\DatabaseConnection;
use ReflectionClass;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Unique extends Constraint
{
    private ?string $table;
    private ?string $column;
    private array $conditions;

    public function __construct(
        string $message = '',
        ?string $table = null,
        ?string $column = null,
        array $conditions = [],
    ) {
        $this->table = $table;
        $this->column = $column;
        $this->conditions = $conditions;
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

        if (property_exists($object, 'id') && $object->id !== null) {
            $sql .= " AND id != ?";
            $params[] = $object->id;
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
            $prop->setAccessible(true);
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