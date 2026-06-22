<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Tests\Fixture;

use Neo\Core\Utils\Notification\Channel\ChannelInterface;
use Neo\Core\Utils\Notification\Enum\NotificationEnum;

final class NoApiKeyChannel implements ChannelInterface
{
    public static function requiredApiKey(): ?string
    {
        return null;
    }

    public function withApiConfig(array $apiConfig): self
    {
        return $this;
    }

    public function setParams(array $params): self
    {
        return $this;
    }

    public function setBody(string $renderedBody): self
    {
        return $this;
    }

    public function send(): NotificationEnum
    {
        return NotificationEnum::SUCCESS;
    }
}