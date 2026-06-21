<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Channel\Slack;

use Neo\Core\Utils\Notification\Channel\ChannelInterface;
use Neo\Core\Utils\Notification\Enum\NotificationEnum;
use Neo\Core\Utils\Notification\Exception\ChannelException;

class SlackChannel implements ChannelInterface
{
    /** @var array<string, mixed> */
    private array $apiConfig = [];

    /** @var array<string, mixed> */
    private array $params = [];

    private string $body = '';

    public static function requiredApiKey(): ?string
    {
        return 'slack';
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

        $defaults = $this->apiConfig['default'] ?? [];

        $payload = ['text' => $this->body];

        $channel = $this->params['channel']  ?? $defaults['channel']  ?? null;
        $username = $this->params['username'] ?? $defaults['username'] ?? null;
        $icon = $this->params['icon']     ?? $defaults['icon']     ?? null;

        if ($channel !== null && $channel !== '') {
            $payload['channel'] = $channel;
        }

        if ($username !== null && $username !== '') {
            $payload['username'] = $username;
        }

        if ($icon !== null && $icon !== '') {
            $payload['icon_emoji'] = $icon;
        }

        try {
            $ch = curl_init($this->apiConfig['webhook_url']);
            curl_setopt_array($ch, [
                \CURLOPT_RETURNTRANSFER => true,
                \CURLOPT_POST => true,
                \CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                \CURLOPT_POSTFIELDS => json_encode($payload, \JSON_THROW_ON_ERROR),
                \CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($error !== '') {
                throw new \RuntimeException("cURL error: {$error}");
            }

            if ($httpCode !== 200 || $response !== 'ok') {
                throw new \RuntimeException(
                    sprintf('Slack API returned HTTP %d: %s', $httpCode, $response)
                );
            }

            return NotificationEnum::SUCCESS;
        } catch (\Throwable $e) {
            throw new ChannelException(
                title: 'Slack Send Failed',
                message: $e->getMessage(),
                code: 500,
                context: ['params' => $this->params],
                previous: $e,
            );
        }
    }

    /**
     * @throws ChannelException
     */
    private function validate(): void
    {
        if (empty($this->apiConfig['enabled'])) {
            throw new ChannelException(
                title: 'Slack Disabled',
                message: "SlackChannel is disabled. Set 'slack.enabled = true' in api.config.php.",
                code: 500,
            );
        }

        if (empty($this->apiConfig['webhook_url'])) {
            throw new ChannelException(
                title: 'Missing Slack Webhook URL',
                message: "SlackChannel requires 'slack.webhook_url' in api.config.php.",
                code: 500,
            );
        }

        if ($this->body === '') {
            throw new ChannelException(
                title: 'Missing Slack Body',
                message: 'SlackChannel body is empty. Did you call setTemplate()?',
                code: 500,
            );
        }
    }
}