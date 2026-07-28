<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Persistence;

use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\ORM\Mapping\ClassMetadata;
use Neo\Core\Database\ORM\Mapping\MetadataFactory;
use Neo\Core\Database\ORM\Platform\AbstractPlatform;

interface EntityManagerInterface
{
    public function persist(object $entity): void;

    public function remove(object $entity): void;

    public function flush(): void;

    public function find(string $className, mixed $id): ?object;

    public function getReference(string $className, mixed $id): object;

    public function getRepository(string $className): EntityRepository;

    public function getClassMetadata(string $className): ClassMetadata;

    public function getMetadataFactory(): MetadataFactory;

    public function getUnitOfWork(): UnitOfWork;

    public function getDatabase(): DatabaseManager;

    public function getPlatform(): AbstractPlatform;

    public function contains(object $entity): bool;

    public function clear(): void;
}