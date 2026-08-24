<?php

declare(strict_types=1);

namespace Neo\Core\Package\Abstract;

use Neo\Core\DI\Container;
use Neo\Core\Package\Interface\PackageInterface;

class AbstractPackage implements PackageInterface
{

    public function getName(): string
    {
        return '';
    }

    public function getPath(): string
    {
        return '';
    }

    public function getControllerPath(): ?string
    {
        return $this->resolve('src/Controller');
    }

    public function getViewPath(): ?string
    {
        return $this->resolve('src/Templates');
    }

    public function getListenersPath(): ?string
    {
        return $this->resolve('src/Listener');
    }

    public function getCronsPath(): ?string
    {
        return $this->resolve('src/Crons');
    }

    public function getCommandsPath(): ?string
    {
        return $this->resolve('src/Command');
    }

    public function getMigrationsPath(): ?string
    {
        return $this->resolve('database/Migration');
    }

    public function getConfigPath(): ?string
    {
        return $this->resolve('config');
    }

    public function getAssetsPath(): ?string
    {
        return $this->resolve('src/Assets');
    }

    public function getTranslationsPath(): ?string
    {
        return $this->resolve('src/Translations');
    }

    public function register(Container $container): void
    {}

    private function resolve(string $relativePath): ?string
    {
        $path = rtrim($this->getPath(), '/\\') . '/' . $relativePath;

        return is_dir($path) ? $path : null;
    }
}