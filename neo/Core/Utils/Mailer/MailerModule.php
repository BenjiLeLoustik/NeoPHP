<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Mailer;

use Neo\Core\DI\Container;
use Neo\Core\Module\AbstractModule;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\Utils\Logger\LoggerModule;
use Neo\Core\View\ViewModule;

class MailerModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            LoggerModule::class,
            ViewModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(Mailer::class, fn(Container $c) => new Mailer($c));
    }

    protected function resolveDependencies(): void
    {
        $this->get(Mailer::class);
    }
}