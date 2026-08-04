<?php

declare(strict_types=1);

namespace Neo\Core\Package\Interface;

use Neo\Core\DI\Container;

interface PackageInterface
{
    public function getName(): string;

    public function getPath(): string;

    public function getControllerPath(): ?string;

    public function getViewPath(): ?string;

    public function getListenersPath(): ?string;

    public function getCronsPath(): ?string;

    public function getCommandsPath(): ?string;

    public function getMigrationsPath(): ?string;

    public function getConfigPath(): ?string;

    public function getAssetsPath(): ?string;

    public function register(Container $container): void;
}