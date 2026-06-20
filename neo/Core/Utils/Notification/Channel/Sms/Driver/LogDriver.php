<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Channel\Sms\Driver;

use Neo\Core\DI\ContainerRegistry;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Utils\Logger\Logger;

class LogDriver implements DriverInterface
{
    /** @var array<string, mixed> $config */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @throws ContainerException
     */
    public function send(string $to, string $body): void
    {
        /** @var Logger $logger */
        $logger = ContainerRegistry::get()->get(Logger::class);

        $logger->channel('sms')->info(
            sprintf('SMS to %s: %s', $to, $body)
        );
    }
}