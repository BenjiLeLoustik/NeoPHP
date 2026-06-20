<?php

namespace Neo\Core\Utils\Notification\Channel\Sms\Driver;

interface DriverInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(array $config);

    public function send(string $to, string $body): void;
}