<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Schema;

use Neo\Core\Database\ORM\Mapping\ClassMetadata;
use Neo\Core\Database\ORM\Persistence\EntityManager;
use Neo\Core\Database\ORM\Type\TypeRegistry;

final class SchemaTool
{
    public function __construct(
        private readonly EntityManager $em,
    ) {}

    public function getSchema(array $entityClasses): array
    {
        $schema = [];

        foreach ($entityClasses as $class) {
            $metadata = $this->em->getClassMetadata($class);
            $schema[$metadata->table] = $this->buildColumns($metadata);
        }

        foreach ($entityClasses as $class) {
            $metadata = $this->em->getClassMetadata($class);
            foreach ($metadata->associationMappings as $assoc) {
                if ($assoc['type'] === ClassMetadata::MANY_TO_MANY && $assoc['isOwningSide']) {
                    $pivot = $assoc['joinTable'];
                    $schema[$pivot['name']] = $this->buildPivotColumns($pivot);
                }
            }
        }

        return $schema;
    }

    public function getForeignKeys(array $entityClasses): array
    {
        $fks = [];

        foreach ($entityClasses as $class) {
            $metadata = $this->em->getClassMetadata($class);
            foreach ($metadata->associationMappings as $assoc) {
                if (!$assoc['isOwningSide'] || !($assoc['type'] & ClassMetadata::TO_ONE)) {
                    continue;
                }
                $targetMeta = $this->em->getClassMetadata($assoc['targetEntity']);
                foreach ($assoc['joinColumns'] as $jc) {
                    $fks[] = [
                        'table' => $metadata->table,
                        'column' => $jc['name'],
                        'referencedTable' => $targetMeta->table,
                        'referencedColumn' => $jc['referencedColumnName'],
                        'onDelete' => $jc['onDelete'],
                        'onUpdate' => $jc['onUpdate'] ?? null,
                    ];
                }
            }
        }

        return $fks;
    }

    public function getIndexes(array $entityClasses): array
    {
        $indexes = [];

        foreach ($entityClasses as $class) {
            $metadata = $this->em->getClassMetadata($class);
            foreach ($metadata->indexes as $index) {
                $indexes[] = [
                    'table' => $metadata->table,
                    'name' => $index['name'] ?? ('idx_' . implode('_', $index['columns'])),
                    'columns' => $index['columns'],
                    'unique' => false,
                ];
            }
            foreach ($metadata->uniqueConstraints as $uc) {
                $indexes[] = [
                    'table' => $metadata->table,
                    'name' => $uc['name'] ?? ('uniq_' . implode('_', $uc['columns'])),
                    'columns' => $uc['columns'],
                    'unique' => true,
                ];
            }
        }

        return $indexes;
    }

    private function buildColumns(ClassMetadata $metadata): array
    {
        $platform = $this->em->getPlatform();
        $columns = [];

        foreach ($metadata->fieldMappings as $mapping) {
            $type = TypeRegistry::get($mapping['type'])->getSQLDeclaration($mapping, $platform);

            $key = '';
            $extra = '';
            if (!empty($mapping['id'])) {
                $key = 'PRI';
                if ($metadata->usesIdGenerator()) {
                    $extra = 'auto_increment';
                }
            } elseif (!empty($mapping['unique'])) {
                $key = 'UNI';
            }

            $columns[] = [
                'name' => $mapping['columnName'],
                'type' => $platform->canonicalizeType($type),
                'nullable' => (bool) $mapping['nullable'],
                'default' => $mapping['default'] ?? null,
                'key' => $key,
                'extra' => $extra,
            ];
        }

        foreach ($metadata->associationMappings as $assoc) {
            if (!$assoc['isOwningSide'] || !($assoc['type'] & ClassMetadata::TO_ONE)) {
                continue;
            }
            $targetMeta = $this->em->getClassMetadata($assoc['targetEntity']);
            $idMapping = $targetMeta->fieldMappings[(string) $targetMeta->identifier];

            foreach ($assoc['joinColumns'] as $jc) {
                $type = TypeRegistry::get($idMapping['type'])->getSQLDeclaration($idMapping, $platform);
                $columns[] = [
                    'name' => $jc['name'],
                    'type' => $platform->canonicalizeType($type),
                    'nullable' => (bool) $jc['nullable'],
                    'default' => null,
                    'key' => '',
                    'extra' => '',
                ];
            }
        }

        return $columns;
    }

    private function buildPivotColumns(array $pivot): array
    {
        $columns = [];
        foreach ([...$pivot['joinColumns'], ...$pivot['inverseJoinColumns']] as $jc) {
            $columns[] = [
                'name' => $jc['name'],
                'type' => 'int',
                'nullable' => false,
                'default' => null,
                'key' => '',
                'extra' => '',
            ];
        }
        return $columns;
    }
}