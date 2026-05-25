<?php

namespace Neo\Core\Profiler\Collector;

use Neo\Core\Profiler\Collector\CollectorInterface;
use Neo\Core\Utils\Mailer;

class MailCollector implements CollectorInterface
{
    public function __construct(private readonly Mailer $mailer) {}

    public function getName(): string
    {
        return 'mail';
    }

    public function collect(): array
    {
        $mails = $this->mailer->getSentMails();

        $sent = array_filter($mails, fn($m) => $m['status'] === 'sent');
        $failed = array_filter($mails, fn($m) => $m['status'] === 'failed');

        $totalDuration = array_sum(array_column($mails, 'duration_ms'));

        return [
            'count' => count($mails),
            'sent' => count($sent),
            'failed' => count($failed),
            'total_ms' => round($totalDuration, 2),
            'mails' => $mails,
        ];
    }
}