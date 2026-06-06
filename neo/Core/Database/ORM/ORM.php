<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM;

use Neo\Core\Database\DatabaseIntrospector;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\Form\FormGenerator;
use Neo\Core\Database\ORM\Model\ModelGenerator;
use Neo\Core\Database\ORM\Repository\RepositoryGenerator;
use Neo\Core\DI\Container;
use Neo\Core\Utils\Config\Config;

class ORM
{
    protected Container $container;
    private DatabaseIntrospector $introspector;
    private ModelGenerator $modelGenerator;
    private RepositoryGenerator $repositoryGenerator;
    private FormGenerator $formGenerator;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->introspector = new DatabaseIntrospector($this->container);
        $this->modelGenerator = new ModelGenerator($this->container);
        $this->repositoryGenerator = new RepositoryGenerator($this->container);
        $this->formGenerator = new FormGenerator($this->container);
    }

    public function generate(): void
    {
        $this->run(
            generateModels: true,
            generateRepositories: true,
            generateForms: true,
            force: false,
        );
    }

    public function generateSelective(
        bool $generateModels = true,
        bool $generateRepositories = true,
        bool $generateForms = true,
        bool $force = false,
    ): void {
        $this->run(
            generateModels: $generateModels,
            generateRepositories: $generateRepositories,
            generateForms: $generateForms,
            force: $force,
            bypassLock: true,
        );
    }

    private function run(
        bool $generateModels = true,
        bool $generateRepositories = true,
        bool $generateForms = true,
        bool $force = false,
        bool $bypassLock = false,
    ): void {
        $env = $this->container->get(Config::class)->from('app')->get('environment') ?? 'prod';
        $isDebug = $env === 'dev';

        $storagePath = $this->container->get('storagePath');
        $cachePath = $storagePath . '/var/cache/orm';
        $lockFile = $cachePath . '/.orm_generated';

        if (!is_dir($cachePath) && !mkdir($cachePath, 0777, true) && !is_dir($cachePath)) {
            throw new DatabaseException(
                title: 'ORM Cache Directory Error',
                message: sprintf("Unable to create the ORM cache directory '%s'.", $cachePath),
                code: 500
            );
        }

        if (!$isDebug && !$bypassLock && !$force && file_exists($lockFile)) {
            return;
        }

        $tables = $this->introspector->getTables();
        $modelNamespace = $this->container->get('modelNamespace');

        foreach ($tables as $tableName) {
            $columns = $this->introspector->getColumns($tableName);

            $modelClassName = $this->modelGenerator->generate(
                table: $tableName,
                columns: $columns,
                write: $generateModels,
            );

            $modelClass = $modelNamespace . '\\' . $modelClassName;

            if ($generateRepositories) {
                $this->repositoryGenerator->generate($modelClass, force: $force);
            }

            if ($generateForms) {
                $this->formGenerator->generate($modelClass);
            }
        }

        if (!$isDebug && !$bypassLock) {
            if (file_put_contents($lockFile, date('Y-m-d H:i:s')) === false) {
                throw new DatabaseException(
                    title: 'ORM Lock File Error',
                    message: sprintf("Unable to write the ORM lock file '%s'.", $lockFile),
                    code: 500
                );
            }
        }
    }
}