<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Channel\Email;

use Neo\Core\Utils\Notification\Channel\ChannelInterface;
use Neo\Core\Utils\Notification\Exception\ChannelException;
use Neo\Core\Utils\Notification\NotificationEnum;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class EmailChannel implements ChannelInterface
{
    /** @var array<string, mixed> */
    private array $apiConfig = [];

    /** @var array<string, mixed> */
    private array $params = [];

    private string $body = '';

    public static function requiredApiKey(): ?string
    {
        return 'mailer';
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

        try {
            $mailer = $this->buildMailer();

            foreach ((array) $this->params['to'] as $address) {
                $mailer->addAddress($address);
            }
            foreach ((array) ($this->params['cc']  ?? []) as $address) {
                $mailer->addCC($address);
            }
            foreach ((array) ($this->params['bcc'] ?? []) as $address) {
                $mailer->addBCC($address);
            }

            $mailer->Subject = $this->params['subject'];
            $mailer->Body = $this->body;
            $mailer->isHTML(true);
            $mailer->send();

            return NotificationEnum::SUCCESS;
        } catch (\Throwable $e) {
            throw new ChannelException(
                title: 'Email Send Failed',
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
                title: 'Mailer Disabled',
                message: "EmailChannel is disabled. Set 'mailer.enabled = true' in api.config.php.",
                code: 500,
            );
        }

        if (empty($this->params['to'])) {
            throw new ChannelException(
                title: 'Missing Email Recipient',
                message: "EmailChannel requires a 'to' param.",
                code: 500,
            );
        }

        if (empty($this->params['subject'])) {
            throw new ChannelException(
                title: 'Missing Email Subject',
                message: "EmailChannel requires a 'subject' param.",
                code: 500,
            );
        }

        if ($this->body === '') {
            throw new ChannelException(
                title: 'Missing Email Body',
                message: 'EmailChannel body is empty. Did you call setTemplate()?',
                code: 500,
            );
        }
    }

    /**
     * @throws Exception
     * @throws ChannelException
     */
    private function buildMailer(): PHPMailer
    {
        $driverName = $this->params['driver'] ?? $this->apiConfig['default'] ?? 'smtp';
        $driver = $this->apiConfig['drivers'][$driverName] ?? null;

        if ($driver === null) {
            throw new ChannelException(
                title: 'Unknown Mailer Driver',
                message: sprintf(
                    "Mailer driver '%s' not found in api.config.php under 'mailer.drivers'.",
                    $driverName,
                ),
                code: 500,
            );
        }

        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $driver['host'] ?? 'localhost';
        $mailer->Port = (int) ($driver['port'] ?? 587);
        $mailer->SMTPSecure = $driver['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->SMTPAuth = !empty($driver['username']);
        $mailer->Username = $driver['username'] ?? '';
        $mailer->Password = $driver['password'] ?? '';
        $mailer->CharSet = 'UTF-8';

        $from = $this->apiConfig['from'] ?? [];
        $mailer->setFrom(
            $from['address'] ?? $driver['username'] ?? '',
            $from['name'] ?? '',
        );

        return $mailer;
    }
}