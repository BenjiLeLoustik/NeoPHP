<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Persistence;

use Neo\Core\Database\ORM\Mapping\ClassMetaData;

/**
 * @template TEntity of object
 */
class EntityRepository
{
    protected ClassMetaData $metadata;

    public function __construct(
        protected readonly EntityManager $em,
        protected readonly string $className,
    ) {
        $this->metadata = $em->getClassMetadata($className);
    }

    /**
     * @return TEntity|null
     */
    public function find(mixed $id): ?object
    {
        return $this->em->find($this->className, $id);
    }

    /**
     * @return list<TEntity>
     */
    public function findAll(): array
    {
        return $this->persister()->loadAll();
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @return list<TEntity>
     */
    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        return $this->persister()->loadAll($this->toColumns($criteria), $orderBy, $limit, $offset);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     * @return TEntity|null
     */
    public function findOneBy(array $criteria, array $orderBy = []): ?object
    {
        $result = $this->persister()->loadAll($this->toColumns($criteria), $orderBy, 1);
        return $result[0] ?? null;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function count(array $criteria = []): int
    {
        return $this->persister()->count($this->toColumns($criteria));
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->em;
    }

    protected function persister(): EntityPersister
    {
        return $this->em->getUnitOfWork()->getEntityPersister($this->className);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array<string, mixed>
     */
    protected function toColumns(array $criteria): array
    {
        $columns = [];
        foreach ($criteria as $field => $value) {
            if ($this->metadata->hasField($field)) {
                $columns[$this->metadata->getColumnName($field)] = $value;
                continue;
            }
            if ($this->metadata->hasAssociation($field)
                && ($this->metadata->associationMappings[$field]['type'] & ClassMetaData::TO_ONE)
                && $this->metadata->associationMappings[$field]['isOwningSide']
            ) {
                $jc = $this->metadata->associationMappings[$field]['joinColumns'][0];
                $columns[$jc['name']] = is_object($value)
                    ? $this->em->getClassMetadata($value::class)->getIdentifierValue($value)
                    : $value;
                continue;
            }
            $columns[$field] = $value;
        }
        return $columns;
    }
}