<?php
declare(strict_types=1);

namespace Neo\Core\Cron;

use Neo\Core\Cron\Runner\CronRunner;
use Neo\Core\Cron\Scanner\CronScanner;
use Neo\Core\DI\Container;
use Neo\Core\Module\AbstractModule;
use Neo\Core\Utils\Logger\LoggerModule;

class CronModule extends AbstractModule
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
}