<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Persistence;

use Neo\Core\Database\Access\Connection\DatabaseConnection;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\Mapping\ClassMetaData;
use PDO;
use Throwable;

final class UnitOfWork
{
    public const int STATE_MANAGED  = 1;
    public const int STATE_NEW      = 2;
    public const int STATE_DETACHED = 3;
    public const int STATE_REMOVED  = 4;

    /** @var array<class-string, array<string, object>> */
    private array $identityMap = [];

    /** @var array<int, int> */
    private array $entityStates = [];

    /** @var array<int, mixed> */
    private array $entityIdentifiers = [];

    /** @var array<int, array<string, mixed>> */
    private array $originalEntityData = [];

    /** @var array<int, object> */
    private array $entityInsertions = [];

    /** @var array<int, object> */
    private array $entityUpdates = [];

    /** @var array<int, object> */
    private array $entityDeletions = [];

    /** @var array<int, array<string, array{mixed, mixed}>> */
    private array $entityChangeSets = [];

    /** @var array<class-string, EntityPersister> */
    private array $persisters = [];

    private ?PDO $pdo = null;

    public function __construct(
        private readonly EntityManager $em,
    ) {}

    public function persist(object $entity): void
    {
        $visited = [];
        $this->doPersist($entity, $visited);
    }

    /**
     * @param array<int, bool> $visited
     */
    private function doPersist(object $entity, array &$visited): void
    {
        $oid = spl_object_id($entity);
        if (isset($visited[$oid])) {
            return;
        }
        $visited[$oid] = true;

        $metadata = $this->em->getClassMetadata($entity::class);
        $state = $this->getEntityState($entity);

        switch ($state) {
            case self::STATE_MANAGED:
                break;

            case self::STATE_NEW:
                $this->persistNew($metadata, $entity);
                break;

            case self::STATE_REMOVED:
                unset($this->entityDeletions[$oid]);
                $this->entityStates[$oid] = self::STATE_MANAGED;
                break;

            case self::STATE_DETACHED:
                throw new DatabaseException(
                    title: 'UnitOfWork Error',
                    message: 'Detached entity cannot be persisted.',
                    code: 500
                );
        }

        $this->cascadePersist($metadata, $entity, $visited);
    }

    private function persistNew(ClassMetaData $metadata, object $entity): void
    {
        $oid = spl_object_id($entity);
        $this->entityStates[$oid] = self::STATE_MANAGED;
        $this->entityInsertions[$oid] = $entity;

        if (!$metadata->usesIdGenerator()) {
            $id = $metadata->getIdentifierValue($entity);
            if ($id !== null) {
                $this->entityIdentifiers[$oid] = $id;
                $this->addToIdentityMap($metadata->name, $id, $entity);
            }
        }
    }

    public function remove(object $entity): void
    {
        $visited = [];
        $this->doRemove($entity, $visited);
    }

    /**
     * @param array<int, bool> $visited
     */
    private function doRemove(object $entity, array &$visited): void
    {
        $oid = spl_object_id($entity);
        if (isset($visited[$oid])) {
            return;
        }
        $visited[$oid] = true;

        $metadata = $this->em->getClassMetadata($entity::class);
        $state = $this->getEntityState($entity);

        switch ($state) {
            case self::STATE_NEW:
                unset($this->entityInsertions[$oid]);
                $this->entityStates[$oid] = self::STATE_NEW;
                break;

            case self::STATE_MANAGED:
                unset($this->entityUpdates[$oid]);
                $this->entityDeletions[$oid] = $entity;
                $this->entityStates[$oid] = self::STATE_REMOVED;
                break;
        }

        $this->cascadeRemove($metadata, $entity, $visited);
    }

    /**
     * @param array<int, bool> $visited
     */
    private function cascadePersist(ClassMetaData $metadata, object $entity, array &$visited): void
    {
        foreach ($metadata->associationMappings as $field => $assoc) {
            if (!$this->cascades($assoc, 'persist')) {
                continue;
            }
            $related = $this->readAssociation($metadata, $entity, $field);
            foreach ($this->toIterable($related) as $relatedEntity) {
                if (is_object($relatedEntity)) {
                    $this->doPersist($relatedEntity, $visited);
                }
            }
        }
    }

    /**
     * @param array<int, bool> $visited
     */
    private function cascadeRemove(ClassMetaData $metadata, object $entity, array &$visited): void
    {
        foreach ($metadata->associationMappings as $field => $assoc) {
            if (!$this->cascades($assoc, 'remove') && empty($assoc['orphanRemoval'])) {
                continue;
            }
            $related = $this->readAssociation($metadata, $entity, $field);
            foreach ($this->toIterable($related) as $relatedEntity) {
                if (is_object($relatedEntity)) {
                    $this->doRemove($relatedEntity, $visited);
                }
            }
        }
    }

    public function commit(): void
    {
        $this->computeChangeSets();

        if ($this->entityInsertions === []
            && $this->entityUpdates === []
            && $this->entityDeletions === []
        ) {
            return;
        }

        $ownsTransaction = !$this->pdo()->inTransaction();
        if ($ownsTransaction) {
            $this->beginTransaction();
        }

        try {
            foreach ($this->getCommitOrder() as $className) {
                $persister = $this->getEntityPersister($className);

                foreach ($this->entityInsertions as $oid => $entity) {
                    if ($entity::class !== $className) {
                        continue;
                    }
                    $this->executeInsert($persister, $className, $entity, $oid);
                }
            }

            foreach ($this->entityUpdates as $oid => $entity) {
                $persister = $this->getEntityPersister($entity::class);
                $persister->update($entity, $this->entityChangeSets[$oid] ?? []);
                $this->refreshOriginalData($entity);
            }

            foreach (array_reverse($this->entityDeletions, true) as $entity) {
                $this->getEntityPersister($entity::class)->delete($entity);
                $this->removeFromIdentityMap($entity);
            }

            if ($ownsTransaction) {
                $this->commitTransaction();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction) {
                $this->rollbackTransaction();
            }
            throw $e;
        }

        $this->entityInsertions = [];
        $this->entityUpdates    = [];
        $this->entityDeletions  = [];
        $this->entityChangeSets = [];
    }

    private function executeInsert(EntityPersister $persister, string $className, object $entity, int $oid): void
    {
        $metadata = $this->em->getClassMetadata($className);
        $generatedId = $persister->insert($entity);

        if ($metadata->usesIdGenerator() && $generatedId !== null) {
            $metadata->setFieldValue($entity, (string) $metadata->identifier, $this->castId($metadata, $generatedId));
        }

        $id = $metadata->getIdentifierValue($entity);
        $this->entityIdentifiers[$oid] = $id;
        $this->addToIdentityMap($className, $id, $entity);
        $this->refreshOriginalData($entity);
    }

    private function computeChangeSets(): void
    {
        foreach ($this->identityMap as $className => $entities) {
            $metadata = $this->em->getClassMetadata($className);

            foreach ($entities as $entity) {
                $oid = spl_object_id($entity);

                if (isset($this->entityInsertions[$oid]) || isset($this->entityDeletions[$oid])) {
                    continue;
                }
                if (($this->entityStates[$oid] ?? null) !== self::STATE_MANAGED) {
                    continue;
                }
                if ($this->em->getProxyFactory()->isUninitialized($entity)) {
                    continue;
                }

                $changeSet = $this->buildChangeSet($metadata, $entity);
                if ($changeSet !== []) {
                    $this->entityChangeSets[$oid] = $changeSet;
                    $this->entityUpdates[$oid] = $entity;
                }
            }
        }
    }

    /**
     * @return array<string, array{mixed, mixed}>
     */
    private function buildChangeSet(ClassMetaData $metadata, object $entity): array
    {
        $oid = spl_object_id($entity);
        $original = $this->originalEntityData[$oid] ?? [];
        $changeSet = [];

        foreach ($metadata->fieldMappings as $field => $_) {
            $new = $metadata->getFieldValue($entity, $field);
            $old = $original[$field] ?? null;
            if ($this->valuesDiffer($old, $new)) {
                $changeSet[$field] = [$old, $new];
            }
        }

        foreach ($metadata->associationMappings as $field => $assoc) {
            if (!$assoc['isOwningSide'] || !($assoc['type'] & ClassMetaData::TO_ONE)) {
                continue;
            }
            $target = $this->readAssociation($metadata, $entity, $field);
            $newId = $target !== null ? $this->extractId($target) : null;
            $oldId = $original[$field] ?? null;
            if ($newId !== $oldId) {
                $changeSet[$field] = [$oldId, $target];
            }
        }

        return $changeSet;
    }

    /**
     * @return list<class-string>
     */
    private function getCommitOrder(): array
    {
        $classes = [];
        foreach ($this->entityInsertions as $entity) {
            $classes[$entity::class] = true;
        }
        $classes = array_keys($classes);

        $inDegree = array_fill_keys($classes, 0);
        $edges = [];

        foreach ($classes as $class) {
            $metadata = $this->em->getClassMetadata($class);
            foreach ($metadata->associationMappings as $assoc) {
                if (!$assoc['isOwningSide'] || !($assoc['type'] & ClassMetaData::TO_ONE)) {
                    continue;
                }
                $target = $assoc['targetEntity'];
                if ($target === $class || !in_array($target, $classes, true)) {
                    continue;
                }

                $edges[$target][] = $class;
                $inDegree[$class]++;
            }
        }

        $queue = [];
        foreach ($inDegree as $class => $deg) {
            if ($deg === 0) {
                $queue[] = $class;
            }
        }

        $ordered = [];
        while ($queue !== []) {
            $class = array_shift($queue);
            $ordered[] = $class;
            foreach ($edges[$class] ?? [] as $dependent) {
                if (--$inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        foreach ($classes as $class) {
            if (!in_array($class, $ordered, true)) {
                $ordered[] = $class;
            }
        }

        return $ordered;
    }

    public function addToIdentityMap(string $className, mixed $id, object $entity): void
    {
        $this->identityMap[$className][$this->idHash($id)] = $entity;
    }

    public function tryGetById(string $className, mixed $id): ?object
    {
        return $this->identityMap[$className][$this->idHash($id)] ?? null;
    }

    /**
     * @param array<string, mixed> $originalData
     */
    public function registerManaged(object $entity, mixed $id, array $originalData): void
    {
        $oid = spl_object_id($entity);
        $this->entityStates[$oid] = self::STATE_MANAGED;
        $this->entityIdentifiers[$oid] = $id;
        $this->originalEntityData[$oid] = $originalData;
        $this->addToIdentityMap($entity::class, $id, $entity);
    }

    private function removeFromIdentityMap(object $entity): void
    {
        $oid = spl_object_id($entity);
        $metadata = $this->em->getClassMetadata($entity::class);
        $id = $this->entityIdentifiers[$oid] ?? $metadata->getIdentifierValue($entity);

        unset(
            $this->identityMap[$entity::class][$this->idHash($id)],
            $this->entityStates[$oid],
            $this->entityIdentifiers[$oid],
            $this->originalEntityData[$oid]
        );
    }

    public function isManaged(object $entity): bool
    {
        return ($this->entityStates[spl_object_id($entity)] ?? null) === self::STATE_MANAGED;
    }

    private function refreshOriginalData(object $entity): void
    {
        $metadata = $this->em->getClassMetadata($entity::class);
        $oid = spl_object_id($entity);
        $snapshot = [];

        foreach ($metadata->fieldMappings as $field => $_) {
            $snapshot[$field] = $metadata->getFieldValue($entity, $field);
        }
        foreach ($metadata->associationMappings as $field => $assoc) {
            if ($assoc['isOwningSide'] && ($assoc['type'] & ClassMetaData::TO_ONE)) {
                $target = $this->readAssociation($metadata, $entity, $field);
                $snapshot[$field] = $target !== null ? $this->extractId($target) : null;
            }
        }

        $this->originalEntityData[$oid] = $snapshot;
    }

    public function clear(): void
    {
        $this->identityMap = [];
        $this->entityStates = [];
        $this->entityIdentifiers = [];
        $this->originalEntityData = [];
        $this->entityInsertions = [];
        $this->entityUpdates = [];
        $this->entityDeletions = [];
        $this->entityChangeSets = [];
    }

    public function getEntityPersister(string $className): EntityPersister
    {
        return $this->persisters[$className] ??= new EntityPersister(
            $this->em,
            $this->em->getClassMetadata($className)
        );
    }

    public function beginTransaction(): void
    {
        $this->pdo()->beginTransaction();
    }

    public function commitTransaction(): void
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->commit();
        }
    }

    public function rollbackTransaction(): void
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->rollBack();
        }
    }

    private function getEntityState(object $entity): int
    {
        $oid = spl_object_id($entity);
        if (isset($this->entityStates[$oid])) {
            return $this->entityStates[$oid];
        }

        $metadata = $this->em->getClassMetadata($entity::class);
        $id = $metadata->getIdentifierValue($entity);
        if ($id !== null && $this->tryGetById($entity::class, $id) !== null) {
            return self::STATE_DETACHED;
        }

        return self::STATE_NEW;
    }

    private function readAssociation(ClassMetaData $metadata, object $entity, string $field): mixed
    {
        return $metadata->getFieldValue($entity, $field);
    }

    private function extractId(object $target): mixed
    {
        $metadata = $this->em->getClassMetadata($target::class);
        return $metadata->getIdentifierValue($target);
    }

    private function castId(ClassMetaData $metadata, mixed $generatedId): mixed
    {
        $type = $metadata->getTypeOfField((string) $metadata->identifier);
        return in_array($type, ['integer', 'smallint', 'bigint'], true)
            ? (int) $generatedId
            : $generatedId;
    }

    /**
     * @param array<string, mixed> $assoc
     */
    private function cascades(array $assoc, string $op): bool
    {
        $cascade = $assoc['cascade'] ?? [];
        return in_array($op, $cascade, true) || in_array('all', $cascade, true);
    }

    /**
     * @return iterable<mixed>
     */
    private function toIterable(mixed $value): iterable
    {
        if ($value === null) {
            return [];
        }
        return is_iterable($value) ? $value : [$value];
    }

    private function valuesDiffer(mixed $old, mixed $new): bool
    {
        if ($old instanceof \DateTimeInterface && $new instanceof \DateTimeInterface) {
            return $old->format('Y-m-d H:i:s') !== $new->format('Y-m-d H:i:s');
        }
        return $old !== $new;
    }

    private function idHash(mixed $id): string
    {
        return is_scalar($id) ? (string) $id : serialize($id);
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= DatabaseConnection::getPdo(null);
    }
}