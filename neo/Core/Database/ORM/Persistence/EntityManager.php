<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Persistence;

use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\Mapping\ClassMetaData;
use Neo\Core\Database\ORM\Mapping\MetadataFactory;
use Neo\Core\Database\ORM\Platform\AbstractPlatform;
use Neo\Core\Database\ORM\Platform\MySQLPlatform;
use Neo\Core\DI\Container;
use Throwable;

final class EntityManager implements EntityManagerInterface
{
    private MetadataFactory $metadataFactory;
    private UnitOfWork $unitOfWork;
    private ProxyFactory $proxyFactory;
    private AbstractPlatform $platform;
    private DatabaseManager $db;

    /** @var array<class-string, EntityRepository<object>> */
    private array $repositories = [];

    public function __construct(
        private readonly Container $container,
        ?DatabaseManager $db = null,
        ?AbstractPlatform $platform = null,
        ?MetadataFactory $metadataFactory = null,
    ) {
        $this->db = $db ?? $container->get(DatabaseManager::class);
        $this->platform = $platform ?? new MySQLPlatform();
        $this->metadataFactory = $metadataFactory ?? new MetadataFactory();
        $this->unitOfWork = new UnitOfWork($this);
        $this->proxyFactory = new ProxyFactory($this);
    }

    public function persist(object $entity): void
    {
        $this->unitOfWork->persist($entity);
    }

    public function remove(object $entity): void
    {
        $this->unitOfWork->remove($entity);
    }

    /**
     * @throws Throwable
     */
    public function flush(): void
    {
        $this->unitOfWork->commit();
    }

    /**
     * @throws DatabaseException
     */
    public function find(string $className, mixed $id): ?object
    {
        $metadata = $this->getClassMetadata($className);

        $managed = $this->unitOfWork->tryGetById($className, $id);
        if ($managed !== null) {
            $this->proxyFactory->initialize($managed);

            return $managed;
        }

        return $this->unitOfWork->getEntityPersister($className)->loadById(
            [$metadata->getSingleIdColumnName() => $id]
        );
    }

    public function getReference(string $className, mixed $id): object
    {
        $managed = $this->unitOfWork->tryGetById($className, $id);
        if ($managed !== null) {
            if ($this->proxyFactory->isUninitialized($managed)) {
                $this->proxyFactory->initialize($managed);
            }

            return $managed;
        }

        $proxy = $this->proxyFactory->getProxy($className, $id);
        $this->unitOfWork->registerManaged($proxy, $id, []);

        return $proxy;
    }

    /**
     * @return EntityRepository<object>
     */
    public function getRepository(string $className): EntityRepository
    {
        if (isset($this->repositories[$className])) {
            return $this->repositories[$className];
        }

        $metadata = $this->getClassMetadata($className);
        $repoClass = $metadata->repositoryClass;

        $repository = $repoClass !== null && class_exists($repoClass)
            ? new $repoClass($this, $className)
            : new EntityRepository($this, $className);

        return $this->repositories[$className] = $repository;
    }

    public function getClassMetadata(string $className): ClassMetaData
    {
        return $this->metadataFactory->getMetadataFor($className);
    }

    public function getMetadataFactory(): MetadataFactory
    {
        return $this->metadataFactory;
    }

    public function getUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWork;
    }

    public function getProxyFactory(): ProxyFactory
    {
        return $this->proxyFactory;
    }

    public function getDatabase(): DatabaseManager
    {
        return $this->db;
    }

    public function getPlatform(): AbstractPlatform
    {
        return $this->platform;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function contains(object $entity): bool
    {
        return $this->unitOfWork->isManaged($entity);
    }

    public function clear(): void
    {
        $this->unitOfWork->clear();
    }

    public function wrapInTransaction(callable $callback): mixed
    {
        $this->unitOfWork->beginTransaction();
        try {
            $result = $callback($this);
            $this->flush();
            $this->unitOfWork->commitTransaction();
            return $result;
        } catch (Throwable $e) {
            $this->unitOfWork->rollbackTransaction();
            throw $e;
        }
    }
}