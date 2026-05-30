<?php
declare(strict_types=1);

namespace Neo\Core\Extension;

use Neo\Core\DI\Container;
use Neo\Core\Module\AbstractModule;
use Neo\Core\View\ViewModule;

class ExtensionModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [
            ViewModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(StringExtension::class, fn(Container $c) => new StringExtension($c));
        $container->set(ArrayExtension::class,  fn() => new ArrayExtension());
        $container->set(NumberExtension::class, fn() => new NumberExtension());
        $container->set(DateExtension::class, fn() => new DateExtension());
        $container->set(FileExtension::class, fn() => new FileExtension());
        $container->set(JsonExtension::class, fn() => new JsonExtension());
        $container->set(UrlExtension::class, fn() => new UrlExtension());
    }

    protected function resolveDependencies(): void
    {
        $this->get(StringExtension::class);
    }
}