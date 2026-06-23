<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\Database\ORM\Repository\AbstractRepository;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;

class EntityManager
{
    protected Container $container;

    /** @var array<class-string<AbstractModel>, AbstractRepository<AbstractModel>> */
    private array $repositories = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getRepository(string $modelClass): AbstractRepository
    {
        if (isset($this->repositories[$modelClass])) {
            return $this->repositories[$modelClass];
        }

        if (!class_exists($modelClass) || !is_subclass_of($modelClass, AbstractModel::class)) {
            throw new DatabaseException(
                title: 'Entity Manager Error',
                message: sprintf(
                    "'%s' is not a valid AbstractModel class.",
                    $modelClass
                ),
                code: 500
            );
        }

        $repositoryClass = $this->resolveRepositoryClass($modelClass);

        $repository = class_exists($repositoryClass)
            ? $this->instantiate($repositoryClass, $modelClass)
            : new class($modelClass) extends AbstractRepository {};

        return $this->repositories[$modelClass] = $repository;
    }

    public function setRepository(string $modelClass, AbstractRepository $repository): void
    {
        $this->repositories[$modelClass] = $repository;
    }

    public function clear(): void
    {
        $this->repositories = [];
    }

    /**
     * @throws ContainerException
     */
    private function resolveRepositoryClass(string $modelClass): string
    {
        $parts = explode('\\', $modelClass);
        $modelName = array_pop($parts);

        $repositoryClass = $this->container->get('repositoryNamespace');

        return rtrim($repositoryClass, '\\') . '\\' . $modelName . 'Repository';
    }

    /**
     * @throws ContainerException
     */
    private function instantiate(string $repositoryClass, string $modelClass): AbstractRepository
    {
        if ($this->container->has($repositoryClass)) {
            /** @var AbstractRepository $repository */
            $repository = $this->container->get($repositoryClass);
            return $repository;
        }

        return new $repositoryClass($modelClass);
    }
}