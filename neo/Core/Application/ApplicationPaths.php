<?php

namespace Neo\Core\Application;

use Neo\Core\DI\Container;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

readonly class ApplicationPaths
{
    public function __construct(
        protected Container $container
    ) {}

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function register(): void
    {
        $appName  = $this->container->get('application');
        $basePath = realpath(__DIR__ . '/../../../');

        $this->container->set('basePath', $basePath);
        $this->container->set('appPath', $basePath . '/src/' . $appName);

        $publicPath = $this->resolvePublicPath($basePath);

        $this->container->set('publicPath', $publicPath);
        $this->container->set('buildsPath', $publicPath . '/builds/');
        $this->container->set('srcPath', $basePath . '/src');
        $this->container->set('storagePath', $basePath . '/src/' . $appName . '/Storage');
        $this->container->set('configsPath', $basePath . '/src/' . $appName . '/Config');
        $this->container->set('viewsPath', $basePath . '/src/' . $appName . '/App/Views');
        $this->container->set('controllersPath', $basePath . '/src/' . $appName . '/App/Controllers');
        $this->container->set('assetsPath', $basePath . '/src/' . $appName . '/Assets/');
        $this->container->set('repositoryPath', $basePath . '/src/' . $appName . '/Database/Repository');
        $this->container->set('modelPath', $basePath . '/src/' . $appName . '/Database/Model');
        $this->container->set('formPath', $basePath . '/src/' . $appName . '/Database/Forms');
        $this->container->set('listenersPath', $basePath . '/src/' . $appName . '/App/Event/Listener');
        $this->container->set('cronsPath', $basePath . '/src/' . $appName . '/App/Crons');
        $this->container->set('manifestFilename', 'manifest.json');
        $this->container->set('controllerNamespace', 'Neo\\Src\\' . $appName . '\\App\\Controllers\\');
        $this->container->set('modelNamespace', 'Neo\\Src\\' . $appName . '\\Database\\Model');
        $this->container->set('repositoryNamespace', 'Neo\\Src\\' . $appName . '\\Database\\Repository');
        $this->container->set('formNamespace', 'Neo\\Src\\' . $appName . '\\Database\\Forms');

        if (!empty($GLOBALS['_NEO_TEST_CONFIGS_PATH'])) {
            $this->container->set('testConfigsPath', $GLOBALS['_NEO_TEST_CONFIGS_PATH']);
        }
    }

    protected function resolvePublicPath(string $basePath): string
    {
        if (is_dir($basePath . '/public_html')) {
            return realpath($basePath . '/public_html');
        }

        if (is_dir($basePath . '/public')) {
            return realpath($basePath . '/public');
        }

        return $basePath . '/public';
    }
}