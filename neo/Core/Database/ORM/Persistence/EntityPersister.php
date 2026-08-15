<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Persistence;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\Mapping\ClassMetaData;
use Neo\Core\Database\ORM\Type\TypeRegistry;

final class EntityPersister
{
    private ObjectHydrator $hydrator;

    public function __construct(
        private EntityManager $em,
        private ClassMetaData $metadata,
    ) {
        $this->hydrator = new ObjectHydrator($em);
    }

    /**
     * @throws DatabaseException
     */
    public function insert(object $entity): ?string
    {
        $platform = $this->em->getPlatform();
        $columns = [];
        $values = [];

        foreach ($this->metadata->fieldMappings as $field => $mapping) {
            if ($mapping['id'] && $this->metadata->usesIdGenerator()) {
                $current = $this->metadata->getFieldValue($entity, $field);
                if ($current === null) {
                    continue;
                }
            }
            $columns[] = $mapping['columnName'];
            $values[] = TypeRegistry::get($mapping['type'])
                ->convertToDatabaseValue($this->metadata->getFieldValue($entity, $field), $platform, $mapping);
        }

        foreach ($this->owningToOneAssociations() as $field => $assoc) {
            $jc = array_first($assoc['joinColumns']);
            $target = $this->metadata->getFieldValue($entity, $field);
            $columns[] = $jc['name'];
            $values[] = $target !== null ? $this->referencedValue($target, $jc['referencedColumnName']) : null;
        }

        $cols = $columns
                |> (fn (array $c): array => array_map($platform->quoteIdentifier(...), $c))
                |> (fn (array $c): string => implode(', ', $c));

        $placeholders = $columns
                |> count(...)
                |> (fn($x) => array_fill(0, $x, '?'))
                |> (fn($x) => implode(', ', $x));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $platform->quoteIdentifier($this->metadata->table),
            $cols,
            $placeholders
        );

        $this->em->getDatabase()->query($sql, $values);

        return $this->metadata->usesIdGenerator()
            ? $this->em->getDatabase()->lastInsertId()
            : null;
    }

    /**
     * @param array<string, array{mixed, mixed}> $changeSet
     * @throws DatabaseException
     */
    public function update(object $entity, array $changeSet): void
    {
        if ($changeSet === []) {
            return;
        }

        $platform = $this->em->getPlatform();
        $sets = [];
        $values = [];

        foreach ($changeSet as $field => [$old, $new]) {
            if ($this->metadata->hasField($field)) {
                $mapping = $this->metadata->fieldMappings[$field];
                $sets[] = $platform->quoteIdentifier($mapping['columnName']) . ' = ?';
                $values[] = TypeRegistry::get($mapping['type'])
                    ->convertToDatabaseValue($new, $platform, $mapping);
                continue;
            }

            if ($this->metadata->hasAssociation($field)) {
                $assoc = $this->metadata->associationMappings[$field];
                if (!$assoc['isOwningSide'] || !($assoc['type'] & ClassMetaData::TO_ONE)) {
                    continue;
                }
                $jc = array_first($assoc['joinColumns']);
                $sets[] = $platform->quoteIdentifier($jc['name']) . ' = ?';
                $values[] = $new !== null ? $this->referencedValue($new, $jc['referencedColumnName']) : null;
            }
        }

        if ($sets === []) {
            return;
        }

        $idColumn = $this->metadata->getSingleIdColumnName();
        $values[] = $this->metadata->getIdentifierValue($entity);

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            $platform->quoteIdentifier($this->metadata->table),
            implode(', ', $sets),
            $platform->quoteIdentifier($idColumn)
        );

        $this->em->getDatabase()->query($sql, $values);
    }

    /**
     * @throws DatabaseException
     */
    public function delete(object $entity): void
    {
        $platform = $this->em->getPlatform();
        $sql = sprintf(
            'DELETE FROM %s WHERE %s = ?',
            $platform->quoteIdentifier($this->metadata->table),
            $platform->quoteIdentifier($this->metadata->getSingleIdColumnName())
        );

        $this->em->getDatabase()->query($sql, [$this->metadata->getIdentifierValue($entity)]);
    }

    /**
     * @param array<string, mixed> $criteria
     * @throws DatabaseException
     */
    public function loadById(array $criteria, ?object $into = null): ?object
    {
        $row = $this->fetchOne($criteria);
        if ($row === null) {
            if ($into !== null) {
                throw new DatabaseException(
                    title: 'Entity Not Found',
                    message: sprintf("No '%s' row matched the referenced identifier.", $this->metadata->name),
                    code: 404
                );
            }
            return null;
        }

        return $this->hydrator->hydrate($this->metadata, $row, $into);
    }

    /**
     * @throws DatabaseException
     */
    public function loadInto(object $entity, mixed $id): void
    {
        $this->loadById([$this->metadata->getSingleIdColumnName() => $id], $entity);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @return list<object>
     */
    public function loadAll(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $platform = $this->em->getPlatform();
        [$where, $values] = $this->buildWhere($criteria);

        $sql = 'SELECT * FROM ' . $platform->quoteIdentifier($this->metadata->table) . $where;

        if ($orderBy !== []) {
            $parts = [];
            foreach ($orderBy as $field => $direction) {
                $col = $this->metadata->getColumnName($field);
                $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $parts[] = $platform->quoteIdentifier($col) . ' ' . $dir;
            }
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }

        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
            if ($offset !== null) {
                $sql .= ' OFFSET ' . $offset;
            }
        }

        $rows = $this->em->getDatabase()->fetchAll($sql, $values);

        return array_map(fn(array $row) => $this->hydrator->hydrate($this->metadata, $row), $rows);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function count(array $criteria = []): int
    {
        $platform = $this->em->getPlatform();
        [$where, $values] = $this->buildWhere($criteria);

        $sql = 'SELECT COUNT(*) AS c FROM ' . $platform->quoteIdentifier($this->metadata->table) . $where;
        $row = $this->em->getDatabase()->fetch($sql, $values);

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @param array<string, mixed> $assoc
     * @return list<object>
     */
    public function loadCollection(array $assoc, mixed $ownerId): array
    {
        if ($assoc['type'] === ClassMetaData::ONE_TO_MANY) {
            $targetMeta = $this->em->getClassMetadata($assoc['targetEntity']);
            $owningAssoc = $targetMeta->associationMappings[$assoc['mappedBy']] ?? null;
            if ($owningAssoc === null) {
                return [];
            }
            $col = $owningAssoc['joinColumns'][0]['name'];
            return $this->em->getUnitOfWork()
                ->getEntityPersister($assoc['targetEntity'])
                ->loadAll([$col => $ownerId]);
        }

        if ($assoc['type'] === ClassMetaData::MANY_TO_MANY) {
            return $this->loadManyToMany($assoc, $ownerId);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $assoc
     * @return list<object>
     */
    private function loadManyToMany(array $assoc, mixed $ownerId): array
    {
        $platform = $this->em->getPlatform();
        $targetMeta = $this->em->getClassMetadata($assoc['targetEntity']);
        $targetPersister = $this->em->getUnitOfWork()->getEntityPersister($assoc['targetEntity']);

        if ($assoc['isOwningSide']) {
            $joinTable = $assoc['joinTable'];
            $ownerColumn = $joinTable['joinColumns'][0]['name'];
            $targetColumn = $joinTable['inverseJoinColumns'][0]['name'];
            $pivot = $joinTable['name'];
        } else {
            $ownerAssoc = $targetMeta->associationMappings[$assoc['mappedBy']];
            $joinTable = $ownerAssoc['joinTable'];
            $ownerColumn = $joinTable['inverseJoinColumns'][0]['name'];
            $targetColumn = $joinTable['joinColumns'][0]['name'];
            $pivot = $joinTable['name'];
        }

        $targetTable = $targetMeta->table;
        $targetId = $targetMeta->getSingleIdColumnName();

        $sql = sprintf(
            'SELECT t.* FROM %s t INNER JOIN %s p ON t.%s = p.%s WHERE p.%s = ?',
            $platform->quoteIdentifier($targetTable),
            $platform->quoteIdentifier($pivot),
            $platform->quoteIdentifier($targetId),
            $platform->quoteIdentifier($targetColumn),
            $platform->quoteIdentifier($ownerColumn)
        );

        $rows = $this->em->getDatabase()->fetchAll($sql, [$ownerId]);
        $hydrator = new ObjectHydrator($this->em);

        return array_map(fn(array $row) => $hydrator->hydrate($targetMeta, $row), $rows);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array<string, mixed>|null
     */
    private function fetchOne(array $criteria): ?array
    {
        $platform = $this->em->getPlatform();
        [$where, $values] = $this->buildWhere($criteria);

        $sql = 'SELECT * FROM ' . $platform->quoteIdentifier($this->metadata->table) . $where . ' LIMIT 1';
        return $this->em->getDatabase()->fetch($sql, $values);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array{string, list<mixed>}
     */
    private function buildWhere(array $criteria): array
    {
        if ($criteria === []) {
            return ['', []];
        }

        $platform = $this->em->getPlatform();
        $clauses = [];
        $values = [];

        foreach ($criteria as $column => $value) {
            if ($value === null) {
                $clauses[] = $platform->quoteIdentifier($column) . ' IS NULL';
                continue;
            }
            $clauses[] = $platform->quoteIdentifier($column) . ' = ?';
            $values[] = $value;
        }

        return [' WHERE ' . implode(' AND ', $clauses), $values];
    }

    /**
     * @return iterable<string, array<string, mixed>>
     */
    private function owningToOneAssociations(): iterable
    {
        foreach ($this->metadata->associationMappings as $field => $assoc) {
            if ($assoc['isOwningSide'] && ($assoc['type'] & ClassMetaData::TO_ONE)) {
                yield $field => $assoc;
            }
        }
    }

    private function referencedValue(object $target, string $referencedColumn): mixed
    {
        $targetMeta = $this->em->getClassMetadata($target::class);
        $field = $targetMeta->getFieldForColumn($referencedColumn) ?? $targetMeta->identifier;

        if ($field === $targetMeta->identifier) {
            return $targetMeta->getIdentifierValue($target);
        }

        return $targetMeta->getFieldValue($target, (string) $field);
    }

    /**
     * @param array<string, mixed> $assoc
     * @param list<mixed> $addedIds
     * @param list<mixed> $removedIds
     */
    public function synchronizeManyToMany(array $assoc, mixed $ownerId, array $addedIds, array $removedIds): void
    {
        if (empty($assoc['isOwningSide']) || $assoc['type'] !== ClassMetaData::MANY_TO_MANY) {
            return;
        }

        $platform = $this->em->getPlatform();
        $joinTable = $assoc['joinTable'];
        $pivot = $platform->quoteIdentifier($joinTable['name']);
        $ownerColumn = $platform->quoteIdentifier($joinTable['joinColumns'][0]['name']);
        $targetColumn = $platform->quoteIdentifier($joinTable['inverseJoinColumns'][0]['name']);

        if ($removedIds !== []) {
            $placeholders = $removedIds
                    |> count(...)
                    |> (fn($x) => array_fill(0, $x, '?'))
                    |> (fn($x) => implode(', ', $x));

            $this->em->getDatabase()->query(
                sprintf('DELETE FROM %s WHERE %s = ? AND %s IN (%s)', $pivot, $ownerColumn, $targetColumn, $placeholders),
                [$ownerId, ...array_values($removedIds)]
            );
        }

        foreach ($addedIds as $targetId) {
            $this->em->getDatabase()->query(
                sprintf('INSERT INTO %s (%s, %s) VALUES (?, ?)', $pivot, $ownerColumn, $targetColumn),
                [$ownerId, $targetId]
            );
        }
    }

    /**
     * @param array<string, mixed> $assoc
     * @throws DatabaseException
     */
    public function clearManyToMany(array $assoc, mixed $ownerId): void
    {
        if (empty($assoc['isOwningSide']) || $assoc['type'] !== ClassMetaData::MANY_TO_MANY) {
            return;
        }

        $platform = $this->em->getPlatform();
        $joinTable = $assoc['joinTable'];
        $this->em->getDatabase()->query(
            sprintf(
                'DELETE FROM %s WHERE %s = ?',
                $platform->quoteIdentifier($joinTable['name']),
                $platform->quoteIdentifier($joinTable['joinColumns'][0]['name'])
            ),
            [$ownerId]
        );
    }
}