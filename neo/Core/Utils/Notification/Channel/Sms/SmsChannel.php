<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Channel\Sms;

use Neo\Core\Utils\Notification\Channel\ChannelInterface;
use Neo\Core\Utils\Notification\Exception\ChannelException;
use Neo\Core\Utils\Notification\NotificationEnum;
use Neo\Core\Utils\Notification\Channel\Sms\Driver\DriverInterface;

class SmsChannel implements ChannelInterface
{
    /** @var array<string, mixed> */
    private array $apiConfig = [];

    /** @var array<string, mixed> */
    private array $params = [];

    private string $body = '';

    public static function requiredApiKey(): ?string
    {
        return 'sms';
    }

    public function withApiConfig(array $apiConfig): static
    {
        $this->apiConfig = $apiConfig;
        return $this;
    }

    public function setParams(array $params): static
    {
        $this->params = $params;
        return $this;
    }

    public function setBody(string $renderedBody): static
    {
        $this->body = $renderedBody;
        return $this;
    }

    public function send(): NotificationEnum
    {
        $this->validate();

        $driverName = $this->params['driver'] ?? $this->apiConfig['default'] ?? 'vonage';
        $driverConfig = $this->apiConfig['drivers'][$driverName] ?? null;

        if ($driverConfig === null) {
            throw new ChannelException(
                title: 'Unknown SMS Driver',
                message: sprintf("SMS driver '%s' not found in api.config.php.", $driverName),
                code: 500,
            );
        }

        $className = __NAMESPACE__ . "\\Driver\\" . ucfirst($driverName) . "Driver";

        if (!class_exists($className)) {
            throw new ChannelException(
                title: 'Driver Class Missing',
                message: sprintf("Driver class '%s' not found.", $className),
                code: 500,
            );
        }

        /** @var DriverInterface $driver */
        $driver = new $className($driverConfig);

        $recipients = (array) $this->params['to'];
        $failed = 0;
        $success = 0;

        foreach ($recipients as $to) {
            try {
                $driver->send($to, $this->body);
                $success++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        if ($success === 0) {
            throw new ChannelException(
                title: 'SMS Send Failed',
                message: 'All SMS recipients failed.',
                code: 500,
                context: ['params' => $this->params],
            );
        }

        return $failed > 0 ? NotificationEnum::PARTIAL : NotificationEnum::SUCCESS;
    }

    /**
     * @throws ChannelException
     */
    private function validate(): void
    {
        if (empty($this->apiConfig['enabled'])) {
            throw new ChannelException(
                title: 'SMS Disabled',
                message: "SmsChannel is disabled. Set 'sms.enabled = true' in api.config.php.",
                code: 500,
            );
        }

        if (empty($this->params['to'])) {
            throw new ChannelException(
                title: 'Missing SMS Recipient',
                message: "SmsChannel requires a 'to' param.",
                code: 500,
            );
        }

        if ($this->body === '') {
            throw new ChannelException(
                title: 'Missing SMS Body',
                message: 'SmsChannel body is empty. Did you call setTemplate()?',
                code: 500,
            );
        }
    }
}