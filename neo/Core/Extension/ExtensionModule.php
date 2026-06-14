<?php
declare(strict_types=1);

namespace Neo\Core\Extension;

use Neo\Core\DI\Container;
use Neo\Core\Extension\Array\ArrayExtension;
use Neo\Core\Extension\Date\DateExtension;
use Neo\Core\Extension\File\FileExtension;
use Neo\Core\Extension\Html\HtmlExtension;
use Neo\Core\Extension\Json\JsonExtension;
use Neo\Core\Extension\Number\NumberExtension;
use Neo\Core\Extension\Path\PathExtension;
use Neo\Core\Extension\String\StringExtension;
use Neo\Core\Extension\Url\UrlExtension;
use Neo\Core\Module\AbstractModule;
use Neo\Core\View\ViewModule;

class ExtensionModule extends AbstractModule
{
    /**
     * @return array<int, class-string>
     */
    public function dependencies(): array
    {
        return [
            ViewModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(StringExtension::class, fn(Container $c) => new StringExtension());
        $container->set(ArrayExtension::class,  fn() => new ArrayExtension());
        $container->set(NumberExtension::class, fn() => new NumberExtension());
        $container->set(DateExtension::class, fn() => new DateExtension());
        $container->set(FileExtension::class, fn() => new FileExtension());
        $container->set(JsonExtension::class, fn() => new JsonExtension());
        $container->set(UrlExtension::class, fn() => new UrlExtension());
        $container->set(PathExtension::class,   fn() => new PathExtension());
        $container->set(HtmlExtension::class,   fn() => new HtmlExtension());

        $container->set(ExtensionViewExtension::class, fn(Container $container) => new ExtensionViewExtension(
            $container->get(StringExtension::class),
            $container->get(ArrayExtension::class),
            $container->get(DateExtension::class),
            $container->get(FileExtension::class),
            $container->get(HtmlExtension::class),
            $container->get(JsonExtension::class),
            $container->get(NumberExtension::class),
            $container->get(PathExtension::class),
            $container->get(UrlExtension::class),
        ));

        $container->tag(ExtensionViewExtension::class, 'twig.extension');
    }

    protected function resolveDependencies(): void
    {
        $this->get(StringExtension::class);
    }
}