<?php
declare(strict_types=1);

namespace Neo\Core\Translation;

use Neo\Core\DI\Container;
use Neo\Core\Http\Client\ClientModule;
use Neo\Core\Module\AbstractModule;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\View\ViewModule;

class TranslationModule extends AbstractModule
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
        $container->set(TranslationViewExtension::class, fn(Container $c) => new TranslationViewExtension($c->get(TranslationManager::class)));
        $container->tag(TranslationViewExtension::class, 'twig.extension');
    }

    protected function resolveDependencies(): void
    {
        TranslationRegistry::registerPath(
            $this->container->get('srcPath') . '/' . $this->container->get('application') . '/Translations'
        );
    }
}