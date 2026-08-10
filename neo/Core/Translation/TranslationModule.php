<?php
declare(strict_types=1);

namespace Neo\Core\Translation;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Client\ClientModule;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Translation\Registry\TranslationRegistry;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\View\ViewModule;

class TranslationModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            ViewModule::class,
            ClientModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(TranslationManager::class, fn(Container $c) => new TranslationManager($c));
    }

    /**
     * @throws ContainerException
     * @throws \ReflectionException
     */
    public function init(Container $container): object
    {
        TranslationRegistry::registerPath(
            $container->get('srcPath') . '/' . $container->get('application') . '/Translations'
        );

        return $container->get(TranslationManager::class);
    }
}