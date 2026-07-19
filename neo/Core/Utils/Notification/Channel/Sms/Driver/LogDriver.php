<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Channel\Sms\Driver;

use Neo\Core\DI\ContainerRegistry;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Utils\Logger\LoggerManager;

class LogDriver implements DriverInterface
{

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {}

    /**
     * @throws ContainerException
     */
    public function send(string $to, string $body): void
    {
        /** @var LoggerManager $logger */
        $logger = ContainerRegistry::get()->get(LoggerManager::class);

        $logger->channel('sms')->info(
            sprintf('SMS to %s: %s', $to, $body)
        );
    }
}