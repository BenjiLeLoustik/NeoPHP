<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Mapping;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\Mapping\Attribute\Column;
use Neo\Core\Database\ORM\Mapping\Attribute\Entity;
use Neo\Core\Database\ORM\Mapping\Attribute\GeneratedValue;
use Neo\Core\Database\ORM\Mapping\Attribute\Id;
use Neo\Core\Database\ORM\Mapping\Attribute\Index;
use Neo\Core\Database\ORM\Mapping\Attribute\JoinColumn;
use Neo\Core\Database\ORM\Mapping\Attribute\JoinTable;
use Neo\Core\Database\ORM\Mapping\Attribute\ManyToMany;
use Neo\Core\Database\ORM\Mapping\Attribute\ManyToOne;
use Neo\Core\Database\ORM\Mapping\Attribute\OneToMany;
use Neo\Core\Database\ORM\Mapping\Attribute\OneToOne;
use Neo\Core\Database\ORM\Mapping\Attribute\Table;
use Neo\Core\Database\ORM\Mapping\Attribute\UniqueConstraint;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

final class MetadataFactory
{
    /** @var array<class-string, ClassMetaData> */
    private array $loaded = [];

    public function getMetadataFor(string $className): ClassMetaData
    {
        if (isset($this->loaded[$className])) {
            return $this->loaded[$className];
        }

        return $this->loaded[$className] = $this->build($className);
    }

    /**
     * @throws \ReflectionException
     * @throws DatabaseException
     */
    private function build(string $className): ClassMetaData
    {
        if (!class_exists($className)) {
            throw new DatabaseException(
                title: 'Mapping Error',
                message: sprintf("Entity class '%s' does not exist.", $className),
                code: 500
            );
        }

        $refl = new ReflectionClass($className);

        $entityAttr = $this->firstAttribute($refl, Entity::class);
        if ($entityAttr === null) {
            throw new DatabaseException(
                title: 'Mapping Error',
                message: sprintf("Class '%s' is not marked with #[Entity].", $className),
                code: 500
            );
        }

        $metadata = new ClassMetaData($className);
        $metadata->repositoryClass = $entityAttr->repositoryClass;
        $metadata->readOnly = $entityAttr->readOnly;
        $metadata->table = $this->resolveTableName($refl, $className);

        foreach ($refl->getAttributes(Index::class) as $attr) {
            $idx = $attr->newInstance();
            $metadata->indexes[] = [
                'name' => $idx->name,
                'columns' => $idx->columns
            ];
        }
        foreach ($refl->getAttributes(UniqueConstraint::class) as $attr) {
            $uc = $attr->newInstance();
            $metadata->uniqueConstraints[] = [
                'name' => $uc->name,
                'columns' => $uc->columns
            ];
        }

        foreach ($refl->getProperties() as $prop) {
            if ($prop->isStatic()) {
                continue;
            }
            $this->mapProperty($metadata, $prop);
        }

        if ($metadata->identifier === null) {
            throw new DatabaseException(
                title: 'Mapping Error',
                message: sprintf("Entity '%s' has no #[Id] property.", $className),
                code: 500
            );
        }

        return $metadata;
    }

    private function mapProperty(ClassMetaData $metadata, ReflectionProperty $prop): void
    {
        $name = $prop->getName();

        $manyToOne = $this->firstAttribute($prop, ManyToOne::class);
        $oneToMany = $this->firstAttribute($prop, OneToMany::class);
        $oneToOne = $this->firstAttribute($prop, OneToOne::class);
        $manyToMany = $this->firstAttribute($prop, ManyToMany::class);

        if ($manyToOne || $oneToMany || $oneToOne || $manyToMany) {
            $this->mapAssociation($metadata, $prop, $manyToOne, $oneToMany, $oneToOne, $manyToMany);
            return;
        }

        $columnAttr = $this->firstAttribute($prop, Column::class);
        $idAttr = $this->firstAttribute($prop, Id::class);

        if ($columnAttr === null && $idAttr === null) {
            return;
        }

        $column = $columnAttr ?? new Column(type: $this->inferType($prop));
        $columnName = $column->name ?? $name;

        $isId = $idAttr !== null;
        $generated = null;
        if ($isId) {
            $genAttr = $this->firstAttribute($prop, GeneratedValue::class);
            $generated = $genAttr->strategy ?? GeneratedValue::NONE;
        }

        $metadata->fieldMappings[$name] = [
            'fieldName' => $name,
            'columnName' => $columnName,
            'type' => $columnAttr !== null ? $column->type : $this->inferType($prop),
            'nullable' => $column->nullable,
            'length' => $column->length,
            'precision' => $column->precision,
            'scale' => $column->scale,
            'unique' => $column->unique,
            'unsigned' => $column->unsigned,
            'default' => $column->default,
            'id' => $isId,
            'generated' => $generated,
            'columnDefinition' => $column->columnDefinition,
            'values' => $column->values,
            'enumClass' => $column->enumClass ?? $this->inferEnumClass($prop),
        ];
        $metadata->columnNames[$name] = $columnName;
        $metadata->fieldNames[$columnName] = $name;

        if ($isId) {
            $metadata->identifier = $name;
            $metadata->idGenerator = $generated ?? GeneratedValue::NONE;
        }
    }

    private function mapAssociation(
        ClassMetaData $metadata,
        ReflectionProperty $prop,
        ?ManyToOne $manyToOne,
        ?OneToMany $oneToMany,
        ?OneToOne $oneToOne,
        ?ManyToMany $manyToMany,
    ): void {
        $field = $prop->getName();

        if ($manyToOne !== null) {
            $metadata->associationMappings[$field] = [
                'fieldName' => $field,
                'type' => ClassMetaData::MANY_TO_ONE,
                'targetEntity' => $manyToOne->targetEntity,
                'isOwningSide' => true,
                'mappedBy' => null,
                'inversedBy' => $manyToOne->inversedBy,
                'fetch' => $manyToOne->fetch,
                'cascade' => $manyToOne->cascade,
                'joinColumns' => $this->readJoinColumns($prop, $field),
                'orphanRemoval'=> false,
            ];
            $this->registerFkColumns($metadata, $field);
            return;
        }

        if ($oneToOne !== null) {
            $isOwning = $oneToOne->mappedBy === null;
            $metadata->associationMappings[$field] = [
                'fieldName' => $field,
                'type' => ClassMetaData::ONE_TO_ONE,
                'targetEntity' => $oneToOne->targetEntity,
                'isOwningSide' => $isOwning,
                'mappedBy' => $oneToOne->mappedBy,
                'inversedBy' => $oneToOne->inversedBy,
                'fetch' => $oneToOne->fetch,
                'cascade' => $oneToOne->cascade,
                'joinColumns' => $isOwning ? $this->readJoinColumns($prop, $field) : [],
                'orphanRemoval'=> $oneToOne->orphanRemoval,
            ];
            if ($isOwning) {
                $this->registerFkColumns($metadata, $field);
            }
            return;
        }

        if ($oneToMany !== null) {
            $metadata->associationMappings[$field] = [
                'fieldName' => $field,
                'type' => ClassMetaData::ONE_TO_MANY,
                'targetEntity' => $oneToMany->targetEntity,
                'isOwningSide' => false,
                'mappedBy' => $oneToMany->mappedBy,
                'inversedBy' => null,
                'fetch' => $oneToMany->fetch,
                'cascade' => $oneToMany->cascade,
                'joinColumns' => [],
                'orphanRemoval' => $oneToMany->orphanRemoval,
            ];
            return;
        }

        if ($manyToMany !== null) {
            $isOwning = $manyToMany->mappedBy === null;
            $joinTable = [];
            if ($isOwning) {
                $jtAttr = $this->firstAttribute($prop, JoinTable::class);
                $joinTable = $this->buildJoinTable($jtAttr, $metadata->table, $manyToMany->targetEntity, $field);
            }
            $metadata->associationMappings[$field] = [
                'fieldName' => $field,
                'type' => ClassMetaData::MANY_TO_MANY,
                'targetEntity' => $manyToMany->targetEntity,
                'isOwningSide' => $isOwning,
                'mappedBy' => $manyToMany->mappedBy,
                'inversedBy' => $manyToMany->inversedBy,
                'fetch' => $manyToMany->fetch,
                'cascade' => $manyToMany->cascade,
                'joinColumns' => [],
                'joinTable' => $joinTable,
                'orphanRemoval' => false,
            ];
        }
    }

    /**
     * @return list<array{
     *     name: string,
     *     referencedColumnName: string,
     *     nullable: bool,
     *     unique: bool,
     *     onDelete: string|null,
     *     onUpdate: string|null
     * }>
     */
    private function readJoinColumns(ReflectionProperty $prop, string $field): array
    {
        $columns = [];
        foreach ($prop->getAttributes(JoinColumn::class) as $attr) {
            $jc = $attr->newInstance();
            $columns[] = [
                'name' => $jc->name ?? ($field . '_id'),
                'referencedColumnName' => $jc->referencedColumnName,
                'nullable' => $jc->nullable,
                'unique' => $jc->unique,
                'onDelete' => $jc->onDelete,
                'onUpdate' => $jc->onUpdate,
            ];
        }

        if ($columns === []) {
            $columns[] = [
                'name' => $field . '_id',
                'referencedColumnName' => 'id',
                'nullable' => true,
                'unique' => false,
                'onDelete' => null,
                'onUpdate' => null,
            ];
        }

        return $columns;
    }

    private function registerFkColumns(ClassMetaData $metadata, string $field): void
    {
        foreach ($metadata->associationMappings[$field]['joinColumns'] as $jc) {
            $col = $jc['name'];
            $metadata->fieldNames[$col] ??= $field;
        }
    }

    /**
     * @return array{
     *     name: string,
     *     joinColumns: list<array<string, mixed>>,
     *     inverseJoinColumns: list<array<string, mixed>>
     * }
     * @throws \ReflectionException
     */
    private function buildJoinTable(?JoinTable $jt, string $ownerTable, string $targetEntity, string $field): array
    {
        $targetShort = strtolower(new ReflectionClass($targetEntity)->getShortName());

        /** @phpstan-ignore nullsafe.neverNull */
        $name = $jt?->name ?? ($ownerTable . '_' . $targetShort);

        $joinColumns = $jt !== null && $jt->joinColumns !== []
            ? array_map($this->joinColumnToArray(...), $jt->joinColumns)
            : [['name' => rtrim($ownerTable, 's') . '_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE']];

        $inverseJoinColumns = $jt !== null && $jt->inverseJoinColumns !== []
            ? array_map($this->joinColumnToArray(...), $jt->inverseJoinColumns)
            : [['name' => $targetShort . '_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE']];

        return [
            'name' => $name,
            'joinColumns' => $joinColumns,
            'inverseJoinColumns' => $inverseJoinColumns,
        ];
    }

    /**
     * @return array{name: string|null, referencedColumnName: string, nullable: bool, onDelete: string|null}
     */
    private function joinColumnToArray(JoinColumn $jc): array
    {
        return [
            'name' => $jc->name,
            'referencedColumnName' => $jc->referencedColumnName,
            'nullable' => $jc->nullable,
            'onDelete' => $jc->onDelete,
        ];
    }

    /**
     * @param ReflectionClass<object>|ReflectionProperty $target
     */
    private function firstAttribute(ReflectionClass|ReflectionProperty $target, string $attributeClass): ?object
    {
        return array_first($target->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF))
            ?->newInstance();
    }

    /**
     * @param ReflectionClass<object> $refl
     */
    private function resolveTableName(ReflectionClass $refl, string $className): string
    {
        $tableAttr = $this->firstAttribute($refl, Table::class);
        if ($tableAttr !== null && $tableAttr->name !== null) {
            return $tableAttr->name;
        }

        $short = $refl->getShortName();
        $snake = $short
                |> (fn (string $s): string => (string) preg_replace('/(?<!^)[A-Z]/', '_$0', $s))
                |> strtolower(...);

        return $snake . 's';
    }

    private function inferType(ReflectionProperty $prop): string
    {
        $type = $prop->getType();
        if (!$type instanceof ReflectionNamedType) {
            return 'string';
        }

        $name = $type->getName();

        if (enum_exists($name) && is_subclass_of($name, \BackedEnum::class)) {
            return 'enum';
        }

        return match ($type->getName()) {
            'int' => 'integer',
            'float' => 'float',
            'bool' => 'boolean',
            'array' => 'json',
            'DateTime', '\DateTime', \DateTime::class => 'datetime',
            default => 'string',
        };
    }

    private function inferEnumClass(ReflectionProperty $prop): ?string
    {
        $type = $prop->getType();
        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

        $name = $type->getName();

        return enum_exists($name) && is_subclass_of($name, \BackedEnum::class)
            ? $name
            : null;
    }
}