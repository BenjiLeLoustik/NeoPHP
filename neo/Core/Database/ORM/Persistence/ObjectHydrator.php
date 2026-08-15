<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Persistence;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\Collection\LazyCollection;
use Neo\Core\Database\ORM\Mapping\ClassMetaData;
use Neo\Core\Database\ORM\Type\TypeRegistry;

final class ObjectHydrator
{
    public function __construct(
        private EntityManager $em,
    ) {
    }

    /**
     * @param ClassMetaData $metadata
     * @param array<string, mixed> $row
     * @param object|null $into
     * @return object
     * @throws DatabaseException
     * @throws \ReflectionException
     */
    public function hydrate(ClassMetaData $metadata, array $row, ?object $into = null): object
    {
        $platform = $this->em->getPlatform();
        $uow = $this->em->getUnitOfWork();

        $idColumn = $metadata->getSingleIdColumnName();
        $idType = TypeRegistry::get((string) $metadata->getTypeOfField((string) $metadata->identifier));
        $id = $idType->convertToPHPValue($row[$idColumn] ?? null, $platform);

        $materializedProxy = false;
        if ($into !== null) {
            $entity = $into;
        } else {
            $existing = $uow->tryGetById($metadata->name, $id);
            if ($existing !== null && !$this->em->getProxyFactory()->isUninitialized($existing)) {
                return $existing;
            }
            if ($existing !== null) {
                $entity = $existing;
                $materializedProxy = true;
            } else {
                $entity = $metadata->newInstance();
            }
        }

        $snapshot = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $column = $mapping['columnName'];
            $raw = array_key_exists($column, $row) ? $row[$column] : null;
            $value = TypeRegistry::get($mapping['type'])->convertToPHPValue($raw, $platform, $mapping);

            $this->writeField($metadata, $entity, $field, $value);
            $snapshot[$field] = $value;
        }

        foreach ($metadata->associationMappings as $field => $assoc) {
            if ($assoc['isOwningSide'] && ($assoc['type'] & ClassMetaData::TO_ONE)) {
                $jc = array_first($assoc['joinColumns']);
                $refValue = $jc !== null ? ($row[$jc['name']] ?? null) : null;

                if ($refValue !== null) {
                    $targetId = $this->castTargetId($assoc['targetEntity'], $refValue);
                    $target = $this->em->getReference($assoc['targetEntity'], $targetId);
                    $this->writeField($metadata, $entity, $field, $target);
                    $snapshot[$field] = $targetId;
                } else {
                    $this->writeField($metadata, $entity, $field, null);
                    $snapshot[$field] = null;
                }
                continue;
            }

            if ($assoc['type'] & ClassMetaData::TO_MANY) {
                $this->writeToManyField($metadata, $entity, $field, $assoc, $id);
            }
        }

        if ($materializedProxy) {
            $metadata->reflClass->markLazyObjectAsInitialized($entity);
        }

        $uow->registerManaged($entity, $id, $snapshot);

        return $entity;
    }

    /**
     * @param array<string, mixed> $assoc
     */
    private function writeToManyField(
        ClassMetaData $metadata,
        object $entity,
        string $field,
        array $assoc,
        mixed $ownerId
    ): void {
        $em = $this->em;
        $loader = static function () use ($em, $entity, $field, $assoc, $ownerId): array {
            $items = $em->getUnitOfWork()
                ->getEntityPersister($assoc['targetEntity'])
                ->loadCollection($assoc, $ownerId);

            if ($assoc['type'] === ClassMetaData::MANY_TO_MANY && !empty($assoc['isOwningSide'])) {
                $em->getUnitOfWork()->snapshotManyToMany($entity, $field, $items);
            }

            return $items;
        };

        try {
            $metadata->setFieldValue($entity, $field, new LazyCollection($loader));
        } catch (\TypeError) {
            // Property type doesn't accept a LazyCollection
            // (e.g. non-typed or incompatible declared type) — skip silently.
        }
    }

    private function writeField(ClassMetaData $metadata, object $entity, string $field, mixed $value): void
    {
        try {
            $metadata->setFieldValue($entity, $field, $value);
        } catch (\TypeError) {
            // Hydrated value's type doesn't match the property's declared type
            // Skip rather than fail the whole hydration.
        }
    }

    private function castTargetId(string $targetEntity, mixed $value): mixed
    {
        try {
            $targetMeta = $this->em->getClassMetadata($targetEntity);
            $type = $targetMeta->getTypeOfField((string) $targetMeta->identifier);
            return in_array($type, ['integer', 'smallint', 'bigint'], true) ? (int) $value : $value;
        } catch (\Throwable) {
            return $value;
        }
    }
}