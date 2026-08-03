<?php

declare(strict_types=1);

namespace Neo\Core\Package;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Package\Interface\PackageInterface;
use Neo\Core\Utils\Config\ConfigModule;

final class PackageModule implements ModuleInterface
{
    /**
     * @return list<class-string>
     */
    public function dependencies(): array
    {
        return [ConfigModule::class];
    }

    public function register(Container $container): void
    {
        $container->set('packages', function (Container $c): array {
            $config = $c->get('package.configModule');

            /** @var array<int, class-string<PackageInterface>> $packageClasses */
            $packageClasses = $config->from('app')->get('packages', []);

            $packages = [];

            foreach ($packageClasses as $packageClass) {
                $package = new $packageClass();
                $package->register($c);
                $packages[] = $package;
            }

            return $packages;
        });
    }

    /**
     * @throws ContainerException
     */
    public function init(Container $container): object
    {
        /** @var array<int, PackageInterface> $packages */
        $packages = $container->get('packages');

        return (object) ['packages' => $packages];
    }
}