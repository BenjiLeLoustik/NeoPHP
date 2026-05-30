<?php
declare(strict_types=1);

namespace Neo\Core\Extension;

use Neo\Core\DI\Container;
use Neo\Core\Extension\Array\ArrayExtension;
use Neo\Core\Extension\Date\DateExtension;
use Neo\Core\Extension\File\FileExtension;
use Neo\Core\Extension\Json\JsonExtension;
use Neo\Core\Extension\Number\NumberExtension;
use Neo\Core\Extension\String\StringExtension;
use Neo\Core\Extension\Url\UrlExtension;
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