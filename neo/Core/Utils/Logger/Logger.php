<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Logger;

use DateTime;
use Neo\Core\DI\Container;
use Neo\Core\Profiler\Profiler;
use Neo\Core\Utils\Config\Config;
use ZipArchive;

class Logger
{
    protected Container $container;
    protected array $config;
    protected string $logDirectory;
    protected string $archiveDirectory;
    protected string $currentChannel = 'app';

    private const LEVELS = [
        'DEBUG' => 100,
        'INFO' => 200,
        'NOTICE' => 250,
        'WARNING' => 300,
        'ERROR' => 400,
        'CRITICAL' => 500,
        'ALERT' => 550,
        'EMERGENCY' => 600,
    ];

    public function __construct(Container $container)
    {
        $this->container = $container;

        $configService = $this->container->get(Config::class);
        $this->config = $configService->from('logger')->all();

        if (empty($this->config['enabled'])) {
            return;
        }

        $storagePath = $this->container->get('storagePath');

        $this->logDirectory = $storagePath . '/logs';
        $this->archiveDirectory = $storagePath . '/logs/archives';

        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0777, true);
        }

        if (!is_dir($this->archiveDirectory)) {
            mkdir($this->archiveDirectory, 0777, true);
        }
    }

    public function channel(string $name): self
    {
        $clone = clone $this;
        $clone->currentChannel = $name;
        return $clone;
    }

    public function log(string $level, string $message, array $context = [], string $origin = 'system'): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $level = strtoupper($level);

        if (!isset(self::LEVELS[$level])) {
            throw new \InvalidArgumentException("Unknown log level: $level");
        }

        $minLevel = strtoupper($this->config['min_level'] ?? 'DEBUG');
        if (self::LEVELS[$level] < self::LEVELS[$minLevel]) {
            return;
        }

        $this->archiveOldLogs();

        $this->rotateIfNeeded();

        $filePath = $this->getLogFilePath();
        $entry = $this->formatMessage($level, $message, $context, $origin);

        if (defined('NEO_PROFILER_ENABLED') && NEO_PROFILER_ENABLED) {
            $lc = Profiler::getInstance()->getCollector('logs');
            $lc?->record($level, $message, $context, $origin);
        }

        file_put_contents($filePath, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public function debug(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        $this->log('DEBUG', $msg, $ctx, $origin);
    }

    public function info(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        $this->log('INFO', $msg, $ctx, $origin);
    }

    public function notice(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        $this->log('NOTICE', $msg, $ctx, $origin);
    }

    public function warning(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        $this->log('WARNING', $msg, $ctx, $origin);
    }

    public function error(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        $this->log('ERROR', $msg, $ctx, $origin);
    }

    public function critical(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        $this->log('CRITICAL', $msg, $ctx, $origin);
    }

    public function alert(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        $this->log('ALERT', $msg, $ctx, $origin);
    }

    public function emergency(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        $this->log('EMERGENCY', $msg, $ctx, $origin);
    }

    private function isEnabled(): bool
    {
        return !empty($this->config['enabled']);
    }

    private function getLogFilePath(): string
    {
        $channelConfig = $this->config['channels'][$this->currentChannel] ?? [];
        $fileName = $channelConfig['name'] ?? $this->currentChannel;
        $extension = $channelConfig['extension'] ?? 'log';

        $rotationType = $this->config['rotation']['type'] ?? 'daily';

        if ($rotationType === 'daily') {
            $date = (new DateTime())->format('Y-m-d');
            return "{$this->logDirectory}/{$fileName}-{$date}.{$extension}";
        }

        return "{$this->logDirectory}/{$fileName}.{$extension}";
    }

    private function rotateIfNeeded(): void
    {
        $rotation = $this->config['rotation'] ?? [];

        if (empty($rotation['enabled']) || ($rotation['type'] ?? '') !== 'size') {
            return;
        }

        $file = $this->getLogFilePath();
        if (!file_exists($file)) {
            return;
        }

        if (filesize($file) < $rotation['max_file_size']) {
            return;
        }

        $rotatedFile = $file . '.' . time();
        rename($file, $rotatedFile);
    }

    private function archiveOldLogs(): void
    {
        if (empty($this->config['archive']['enabled'])) {
            return;
        }

        $activeFile = $this->getLogFilePath();
        $files = glob($this->logDirectory . '/*.log');

        foreach ($files as $file) {
            if ($file === $activeFile) {
                continue;
            }

            $this->archiveFile($file);
        }
    }

    private function archiveFile(string $file): void
    {
        $ext = $this->config['archive']['extension'] ?? 'zip';

        $dt = new DateTime();
        $year = $dt->format('Y');
        $month = $dt->format('m');

        $archivePath = "{$this->archiveDirectory}/{$year}/{$month}";
        if (!is_dir($archivePath)) {
            mkdir($archivePath, 0777, true);
        }

        $baseName = basename($file);
        $zipPath = "{$archivePath}/{$baseName}.{$ext}";

        if (file_exists($zipPath)) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            return;
        }

        $zip->addFile($file, $baseName);
        $zip->close();

        unlink($file);
    }

    private function formatMessage(string $level, string $message, array $context, string $origin): string
    {
        $format = $this->config['log_format'] ?? '[{%datetime%}][{%level_name%}] {%message%}';

        $replace = [
            '{%datetime%}' => (new DateTime())->format('Y-m-d H:i:s'),
            '{%level_name%}' => $level,
            '{%level_code%}' => self::LEVELS[$level],
            '{%origin%}' => $origin,
            '{%message%}' => $message,
            '{%context%}' => $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : '',
        ];

        return str_replace(array_keys($replace), array_values($replace), $format);
    }
}
