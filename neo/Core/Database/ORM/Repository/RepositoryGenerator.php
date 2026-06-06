<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Repository;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\DI\Container;

class RepositoryGenerator
{
    protected Container $container;
    private string $appName;
    private string $repoDir;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->appName = $this->container->get('application');
        $this->repoDir = $this->container->get('repositoryPath');

        if (!is_dir($this->repoDir) && !mkdir($this->repoDir, 0777, true) && !is_dir($this->repoDir)) {
            throw new DatabaseException(
                title: 'Repository Generator Error',
                message: sprintf("Unable to create the repositories directory '%s'.", $this->repoDir),
                code: 500
            );
        }
    }

    public function generate(string $modelClass, bool $force = false): string
    {
        $modelParts = explode('\\', $modelClass);
        $modelName = array_pop($modelParts);
        $modelNamespace = implode('\\', $modelParts);
        $repoClassName = $modelName . 'Repository';
        $namespaceRepo = $this->container->get('repositoryNamespace');

        $code = <<<PHP
<?php
declare(strict_types=1);

namespace $namespaceRepo;

use Neo\Core\Database\ORM\Repository\AbstractRepository;
use $modelNamespace\\$modelName;

class $repoClassName extends AbstractRepository
{
    protected string \$modelClass = $modelName::class;
}

PHP;

        $file = "{$this->repoDir}/{$repoClassName}.php";

        if (!$force && file_exists($file)) {
            return $repoClassName;
        }

        if (!file_exists($file) && file_put_contents($file, $code) === false) {
            throw new DatabaseException(
                title: 'Repository Generator Error',
                message: sprintf("Unable to generate the repository '%s' in '%s'.", $repoClassName, $this->repoDir),
                code: 500
            );
        }

        return $repoClassName;
    }
}