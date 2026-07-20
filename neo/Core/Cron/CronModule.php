<?php
declare(strict_types=1);

namespace Neo\Core\Cron;

use Neo\Core\Cron\Runner\CronRunner;
use Neo\Core\Cron\Scanner\CronScanner;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Utils\Logger\LoggerModule;

class CronModule implements ModuleInterface
{
    /**
     * @return list<class-string>
     */
    public function dependencies(): array
    {
        return [
            LoggerModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(CronScanner::class, fn() => new CronScanner());
        $container->set(CronRunner::class, fn(Container $c) => new CronRunner($c));
    }

    /**
     * @throws ContainerException
     */
    public function init(Container $container): object
    {
        return $container->get(CronRunner::class);
    }
}