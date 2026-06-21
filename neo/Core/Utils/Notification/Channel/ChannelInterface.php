<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Channel;

use Neo\Core\Utils\Notification\Enum\NotificationEnum;
use Neo\Core\Utils\Notification\Exception\ChannelException;

interface ChannelInterface
{
    public static function requiredApiKey(): ?string;

    /**
     * @param array<string, mixed> $apiConfig
     */
    public function withApiConfig(array $apiConfig): static;

    /**
     * @param array<string, mixed> $params
     */
    public function setParams(array $params): static;

    public function setBody(string $renderedBody): static;

    /**
     * @throws ChannelException
     */
    public function send(): NotificationEnum;
}