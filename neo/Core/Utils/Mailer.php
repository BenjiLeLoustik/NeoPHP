<?php
declare(strict_types=1);

namespace Neo\Core\Utils;

use Neo\Core\DI\Container;
use Neo\Core\Utils\Config\Config;
use Neo\Core\Utils\Logger\Logger;
use Neo\Core\View\View;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    private string $to = '';
    private string $toName = '';
    private string $subject = '';
    private string $body = '';
    private array $cc = [];
    private array $bcc = [];
    private array $attachments = [];
    private array $sentMails = [];
    private bool $enabled;
    private array $config;

    public function __construct(private Container $container)
    {
        $this->config = $container->get(Config::class)->from('mailer')->all();
        $this->enabled = $this->config['enabled'] ?? false;
    }

    public function to(string $address, string $name = ''): static
    {
        $this->to = $address;
        $this->toName = $name;
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function body(string $html): static
    {
        $this->body = $html;
        return $this;
    }

    public function template(string $template, array $data = []): static
    {
        $this->body = $this->container->get(View::class)->render($template, $data);
        return $this;
    }

    public function cc(string $address, string $name = ''): static
    {
        $this->cc[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function bcc(string $address, string $name = ''): static
    {
        $this->bcc[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function attach(string $path, string $name = ''): static
    {
        $this->attachments[] = ['path' => $path, 'name' => $name];
        return $this;
    }

    public function send(): bool
    {
        if (!$this->enabled) {
            $this->container->get(Logger::class)->warning('Mailer is disabled, mail not sent.', [
                'to' => $this->to,
                'subject' => $this->subject,
            ], 'Mailer');

            $this->reset();
            return false;
        }

        $driver = $this->config['default'] ?? 'smtp';
        $driverConfig = $this->config['drivers'][$driver] ?? [];
        $from = $this->config['from'] ?? [];
        $startTime = microtime(true);

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $driverConfig['host'] ?? '';
            $mail->SMTPAuth = true;
            $mail->Username = $driverConfig['username'] ?? '';
            $mail->Password = $driverConfig['password'] ?? '';
            $mail->SMTPSecure = $driverConfig['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $driverConfig['port'] ?? 587;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($from['address'] ?? '', $from['name'] ?? '');
            $mail->addAddress($this->to, $this->toName);

            foreach ($this->cc as $cc) {
                $mail->addCC($cc['address'], $cc['name']);
            }

            foreach ($this->bcc as $bcc) {
                $mail->addBCC($bcc['address'], $bcc['name']);
            }

            foreach ($this->attachments as $attachment) {
                $mail->addAttachment($attachment['path'], $attachment['name']);
            }

            $mail->isHTML(true);
            $mail->Subject = $this->subject;
            $mail->Body = $this->body;

            $mail->send();

            $this->sentMails[] = [
                'to' => $this->to,
                'subject' => $this->subject,
                'status' => 'sent',
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];

            $this->reset();
            return true;

        } catch (Exception $e) {
            $this->sentMails[] = [
                'to' => $this->to,
                'subject' => $this->subject,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];

            $this->container->get(Logger::class)->error($e->getMessage(), [
                'to' => $this->to,
                'subject' => $this->subject,
            ], 'Mailer');

            $this->reset();
            return false;
        }
    }

    public function getSentMails(): array
    {
        return $this->sentMails;
    }

    private function reset(): void
    {
        $this->to = '';
        $this->toName = '';
        $this->subject = '';
        $this->body = '';
        $this->cc = [];
        $this->bcc = [];
        $this->attachments = [];
    }
}