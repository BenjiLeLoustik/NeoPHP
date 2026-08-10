<?php

namespace Neo\Core\Tools\Markdown;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\View\ViewModule;

class MarkdownModule implements ModuleInterface
{

    /**
     * @return list<class-string>
     */
    public function dependencies(): array
    {
        return [
            ViewModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(MarkdownManager::class, fn(Container $container) => new MarkdownManager($container));
    }

    /**
     * @throws ContainerException
     * @throws \ReflectionException
     */
    public function init(Container $container): object
    {
        return $container->get(MarkdownManager::class);
    }
}