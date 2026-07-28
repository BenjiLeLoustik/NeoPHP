<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Mapping;

use ReflectionClass;
use ReflectionProperty;

/**
 * @phpstan-type FieldMapping array<string, mixed>
 * @phpstan-type AssociationMapping array<string, mixed>
 */
final class ClassMetaData
{
    public const int MANY_TO_ONE  = 1;
    public const int ONE_TO_MANY  = 2;
    public const int ONE_TO_ONE   = 4;
    public const int MANY_TO_MANY = 8;

    public const int TO_ONE = self::MANY_TO_ONE | self::ONE_TO_ONE;
    public const int TO_MANY = self::ONE_TO_MANY | self::MANY_TO_MANY;

    public string $table = '';

    public ?string $repositoryClass = null;

    public bool $readOnly = false;

    /** @var array<string, FieldMapping> */
    public array $fieldMappings = [];

    /** @var array<string, string> */
    public array $columnNames = [];

    /** @var array<string, string> */
    public array $fieldNames = [];

    public ?string $identifier = null;

    public string $idGenerator = 'NONE';

    /** @var array<string, AssociationMapping> */
    public array $associationMappings = [];

    /** @var list<array{name?: string, columns: list<string>}> */
    public array $indexes = [];

    /** @var list<array{name?: string, columns: list<string>}> */
    public array $uniqueConstraints = [];

    /** @var ReflectionClass<object> */
    public ReflectionClass $reflClass;

    /** @var array<string, ReflectionProperty> */
    private array $reflProps = [];

    public function __construct(
        public readonly string $name,
    ) {
        $this->reflClass = new ReflectionClass($name);
    }

    public function hasField(string $fieldName): bool
    {
        return isset($this->fieldMappings[$fieldName]);
    }

    public function getColumnName(string $fieldName): string
    {
        return $this->columnNames[$fieldName] ?? $fieldName;
    }

    public function getFieldForColumn(string $columnName): ?string
    {
        return $this->fieldNames[$columnName] ?? null;
    }

    public function getTypeOfField(string $fieldName): ?string
    {
        return $this->fieldMappings[$fieldName]['type'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getFieldNames(): array
    {
        return array_keys($this->fieldMappings);
    }

    public function isIdentifier(string $fieldName): bool
    {
        return $this->identifier === $fieldName;
    }

    public function getSingleIdColumnName(): string
    {
        return $this->getColumnName((string) $this->identifier);
    }

    public function usesIdGenerator(): bool
    {
        return $this->idGenerator === 'AUTO' || $this->idGenerator === 'IDENTITY';
    }

    public function hasAssociation(string $fieldName): bool
    {
        return isset($this->associationMappings[$fieldName]);
    }

    public function isSingleValuedAssociation(string $fieldName): bool
    {
        return isset($this->associationMappings[$fieldName])
            && (bool) ($this->associationMappings[$fieldName]['type'] & self::TO_ONE);
    }

    public function isCollectionValuedAssociation(string $fieldName): bool
    {
        return isset($this->associationMappings[$fieldName])
            && (bool) ($this->associationMappings[$fieldName]['type'] & self::TO_MANY);
    }

    /**
     * @return array<string, AssociationMapping>
     */
    public function getAssociationMappings(): array
    {
        return $this->associationMappings;
    }

    public function getFieldValue(object $entity, string $fieldName): mixed
    {
        $prop = $this->getReflProperty($fieldName);

        if (!$prop->isInitialized($entity)) {
            return null;
        }

        return $prop->getValue($entity);
    }

    public function setFieldValue(object $entity, string $fieldName, mixed $value): void
    {
        $this->getReflProperty($fieldName)->setValue($entity, $value);
    }

    public function getIdentifierValue(object $entity): mixed
    {
        $prop = $this->getReflProperty((string) $this->identifier);

        if ($this->reflClass->isUninitializedLazyObject($entity)) {
            return $prop->getRawValueWithoutLazyInitialization($entity);
        }

        return $prop->isInitialized($entity) ? $prop->getValue($entity) : null;
    }

    public function newInstance(): object
    {
        return $this->reflClass->newInstanceWithoutConstructor();
    }

    public function getReflProperty(string $fieldName): ReflectionProperty
    {
        if (!isset($this->reflProps[$fieldName])) {
            $prop = $this->reflClass->getProperty($fieldName);
            $this->reflProps[$fieldName] = $prop;
        }

        return $this->reflProps[$fieldName];
    }
}