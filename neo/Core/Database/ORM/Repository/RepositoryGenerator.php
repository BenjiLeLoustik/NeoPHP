<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Repository;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\DI\Container;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class RepositoryGenerator
{
    protected Container $container;
    private string $repoDir;
    private ?string $subNamespace = null;
    private string $baseRepoDir;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws DatabaseException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->repoDir = $this->container->get('repositoryPath');
        $this->baseRepoDir = $this->container->get('repositoryPath');
        $this->repoDir = $this->baseRepoDir;

        if (!is_dir($this->repoDir) && !mkdir($this->repoDir, 0777, true) && !is_dir($this->repoDir)) {
            throw new DatabaseException(
                title: 'Repository Generator Error',
                message: sprintf("Unable to create the repositories directory '%s'.", $this->repoDir),
                code: 500
            );
        }
    }

    public function setConnection(string $connection): void
    {
        $this->subNamespace = str_replace(' ', '', ucwords(str_replace('_', ' ', $connection)));
        $this->repoDir = $this->baseRepoDir . '/' . $this->subNamespace;

        if (!is_dir($this->repoDir) && !mkdir($this->repoDir, 0777, true) && !is_dir($this->repoDir)) {
            throw new DatabaseException(
                title: 'Repository Generator Error',
                message: sprintf("Unable to create the repositories directory '%s'.", $this->repoDir),
                code: 500
            );
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws DatabaseException
     * @throws NotFoundExceptionInterface
     */
    public function generate(string $modelClass, bool $force = false): string
    {
        $modelParts = explode('\\', $modelClass);
        $modelName = array_pop($modelParts);

        $modelNamespace = $this->subNamespace !== null
            ? $this->container->get('modelNamespace') . '\\' . $this->subNamespace
            : implode('\\', $modelParts);

        $repoClassName = $modelName . 'Repository';

        $namespaceRepo = $this->container->get('repositoryNamespace')
            . ($this->subNamespace !== null ? '\\' . $this->subNamespace : '');

        $code = <<<PHP
<?php
declare(strict_types=1);

namespace $namespaceRepo;

use Neo\Core\Database\ORM\Repository\AbstractRepository;
use $modelNamespace\\$modelName;

class $repoClassName extends AbstractRepository
{
    protected string \$modelClass = $modelName::class;
    
    /*
     * Exemples :
     *
     * public function findActive(): static
     * {
     *     \$this->builder = \$this->qb()->where('active', '=', 1);
     *     \$rows = \$this->builder->get();
     *     \$this->hydrateMany(\$rows);
     *     return \$this;
     * }
     *
     * public function findBySlug(string \$slug): ?$modelName
     * {
     *     return \$this->findBy('slug', \$slug);
     * }
     *
     * // With relations :
     * // \$this->with('relation')->findAll()->getModels();
     *
     * // With pagination :
     * // \$this->qb()->where('active', '=', 1);
     * // return \$this->paginate(perPage: 15);
     */
   
}

PHP;

        $file = "{$this->repoDir}/{$repoClassName}.php";

        if (!$force && file_exists($file)) {
            return $repoClassName;
        }

        if (file_put_contents($file, $code) === false) {
            throw new DatabaseException(
                title: 'Repository Generator Error',
                message: sprintf("Unable to generate the repository '%s' in '%s'.", $repoClassName, $this->repoDir),
                code: 500
            );
        }

        return $repoClassName;
    }
}