<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

use Neo\Core\Utils\Notification\NotificationEnum;

/**
 * Collecteur de notifications pour le Profiler.
 *
 * Enregistre chaque notification envoyée via NotificationManager::doSend().
 * NotificationManager appelle record() après chaque envoi.
 *
 * Structure d'une entrée :
 * [
 *   'channel'   => 'EmailChannel',
 *   'template'  => 'emails/welcome.html.twig',
 *   'status'    => 'success' | 'failed' | 'partial' | 'skipped',
 *   'duration_ms' => 42.3,
 *   'error'     => null | 'message d\'erreur',
 * ]
 */
class NotificationCollector implements CollectorInterface
{
    /**
     * @var array<int, array{
     *     channel: string,
     *     template: string,
     *     status: string,
     *     duration_ms: float,
     *     error: string|null,
     * }>
     */
    private array $entries = [];

    public function getName(): string
    {
        return 'mail';
    }

    public function record(
        string $channelClass,
        string $template,
        NotificationEnum $status,
        float $durationMs,
        ?string $error = null,
    ): void {
        $this->entries[] = [
            'channel' => class_basename($channelClass),
            'template' => $template,
            'status' => $status->value,
            'duration_ms' => round($durationMs, 2),
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $sent   = array_filter($this->entries, fn($e) => $e['status'] === NotificationEnum::SUCCESS->value);
        $failed = array_filter($this->entries, fn($e) => $e['status'] === NotificationEnum::FAILED->value);
        $total  = array_sum(array_column($this->entries, 'duration_ms'));

        $mails = array_map(fn($e) => [
            'to' => $e['channel'],
            'subject' => $e['template'],
            'status' => $e['status'] === NotificationEnum::SUCCESS->value ? 'sent' : $e['status'],
            'duration_ms' => $e['duration_ms'],
            'error' => $e['error'],
        ], $this->entries);

        return [
            'count' => count($this->entries),
            'sent' => count($sent),
            'failed' => count($failed),
            'total_ms' => round($total, 2),
            'mails' => array_values($mails),
        ];
    }
}

if (!function_exists('class_basename')) {
    function class_basename(string $class): string
    {
        return basename(str_replace('\\', '/', $class));
    }
}