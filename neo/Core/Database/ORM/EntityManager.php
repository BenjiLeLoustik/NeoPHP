<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\Database\ORM\Repository\AbstractRepository;
use Neo\Core\DI\Container;

class EntityManager
{
    protected Container $container;

    /** @var array<class-string<AbstractModel>, AbstractRepository<AbstractModel>> */
    private array $repositories = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @template T of AbstractModel
     * @param class-string<T> $modelClass
     * @return AbstractRepository<T>
     * @throws DatabaseException
     */
    public function getRepository(string $modelClass): AbstractRepository
    {
        if (isset($this->repositories[$modelClass])) {
            /** @var AbstractRepository<T> */
            return $this->repositories[$modelClass];
        }

        if (!class_exists($modelClass) || !is_subclass_of($modelClass, AbstractModel::class)) {
            throw new DatabaseException(
                title: 'Entity Manager Error',
                message: sprintf("'%s' is not a valid AbstractModel class.", $modelClass),
                code: 500
            );
        }

        $repositoryClass = $this->resolveRepositoryClass($modelClass);

        $repository = class_exists($repositoryClass)
            ? $this->instantiate($repositoryClass, $modelClass)
            : new class($modelClass) extends AbstractRepository {};

        return $this->repositories[$modelClass] = $repository;
    }

    /**
     * Allows manually binding a repository instance for a model class,
     * useful for testing or for repositories with custom dependencies.
     *
     * @template T of AbstractModel
     * @param class-string<T> $modelClass
     * @param AbstractRepository<T> $repository
     */
    public function setRepository(string $modelClass, AbstractRepository $repository): void
    {
        $this->repositories[$modelClass] = $repository;
    }

    public function clear(): void
    {
        $this->repositories = [];
    }

    /**
     * @param class-string<AbstractModel> $modelClass
     * @return class-string
     */
    private function resolveRepositoryClass(string $modelClass): string
    {
        $parts = explode('\\', $modelClass);
        $modelName = array_pop($parts);

        $repositoryNamespace = $this->container->get('repositoryNamespace');

        return rtrim($repositoryNamespace, '\\') . '\\' . $modelName . 'Repository';
    }

    /**
     * @template T of AbstractModel
     * @param class-string $repositoryClass
     * @param class-string<T> $modelClass
     * @return AbstractRepository<T>
     */
    private function instantiate(string $repositoryClass, string $modelClass): AbstractRepository
    {
        if ($this->container->has($repositoryClass)) {
            /** @var AbstractRepository<T> $repository */
            $repository = $this->container->get($repositoryClass);
            return $repository;
        }

        return new $repositoryClass($modelClass);
    }
}