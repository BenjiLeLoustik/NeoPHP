<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Logger;

use DateTime;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Profiler\ProfilerManager;
use ZipArchive;

class LoggerManager
{
    protected Container $container;

    /** @var array<string, mixed> */
    protected array $config;

    protected string $logDirectory;

    protected string $archiveDirectory;

    protected string $currentChannel = 'app';

    private const array LEVELS = [
        'DEBUG' => 100,
        'INFO' => 200,
        'NOTICE' => 250,
        'WARNING' => 300,
        'ERROR' => 400,
        'CRITICAL' => 500,
        'ALERT' => 550,
        'EMERGENCY' => 600,
    ];

    /**
     * @throws ContainerException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $configService = $this->container->get('logger.configModule');
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

    /**
     * @param array<string, mixed> $context
     */
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

        $this->rotateIfNeeded();

        $this->archiveOldLogs();

        $filePath = $this->getLogFilePath();
        $entry = $this->formatMessage($level, $message, $context, $origin);

        if (defined('NEO_PROFILER_ENABLED') && NEO_PROFILER_ENABLED) {
            $lc = ProfilerManager::getInstance()->getCollector('logs');
            $lc?->record($level, $message, $context, $origin);
        }

        file_put_contents($filePath, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function debug(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        ($channel !== null ? $this->channel($channel) : $this)->log('DEBUG', $msg, $ctx, $origin);
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function info(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        ($channel !== null ? $this->channel($channel) : $this)->log('INFO', $msg, $ctx, $origin);
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function notice(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        ($channel !== null ? $this->channel($channel) : $this)->log('NOTICE', $msg, $ctx, $origin);
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function warning(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        ($channel !== null ? $this->channel($channel) : $this)->log('WARNING', $msg, $ctx, $origin);
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function error(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        ($channel !== null ? $this->channel($channel) : $this)->log('ERROR', $msg, $ctx, $origin);
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function critical(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        ($channel !== null ? $this->channel($channel) : $this)->log('CRITICAL', $msg, $ctx, $origin);
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function alert(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        ($channel !== null ? $this->channel($channel) : $this)->log('ALERT', $msg, $ctx, $origin);
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function emergency(string $msg, array $ctx = [], string $origin = 'system', ?string $channel = null): void
    {
        ($channel !== null ? $this->channel($channel) : $this)->log('EMERGENCY', $msg, $ctx, $origin);
    }

    private function isEnabled(): bool
    {
        if (empty($this->config['enabled'])) {
            return false;
        }

        $channelConfig = $this->config['channels'][$this->currentChannel] ?? [];

        if (isset($channelConfig['enabled']) && $channelConfig['enabled'] === false) {
            return false;
        }

        return true;
    }

    private function getLogFilePath(): string
    {
        $channelConfig = $this->config['channels'][$this->currentChannel] ?? [];
        $fileName = $channelConfig['name'] ?? $this->currentChannel;
        $extension = $channelConfig['extension'] ?? 'log';

        $rotationType = $this->config['rotation']['type'] ?? 'daily';

        if ($rotationType === 'daily') {
            $date = new DateTime()->format('Y-m-d');
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

        if (filesize($file) < ($rotation['max_file_size'] ?? PHP_INT_MAX)) {
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

        $activeFiles = $this->activeFilePaths();
        $files = glob($this->logDirectory . '/*');

        $groups = [];
        foreach ($files as $file) {
            if (!is_file($file) || in_array($file, $activeFiles, true)) {
                continue;
            }

            $date = $this->extractDateFromFile($file);
            $groups[$date][] = $file;
        }

        foreach ($groups as $date => $groupFiles) {
            $this->archiveFilesForDate($date, $groupFiles);
        }
    }

    private function extractDateFromFile(string $file): string
    {
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', basename($file), $matches)) {
            return $matches[1];
        }

        $fileTime = filemtime($file) ?: time();
        return date('Y-m-d', $fileTime);
    }

    /**
     * @return array<int, string>
     */
    private function activeFilePaths(): array
    {
        $channels = $this->config['channels'] ?? [];

        if (!isset($channels[$this->currentChannel])) {
            $channels[$this->currentChannel] = [];
        }

        $rotationType = $this->config['rotation']['type'] ?? 'daily';
        $paths = [];

        foreach ($channels as $name => $conf) {
            $fileName = $conf['name'] ?? $name;
            $extension = $conf['extension'] ?? 'log';

            if ($rotationType === 'daily') {
                $date = date('Y-m-d');
                $paths[] = "{$this->logDirectory}/{$fileName}-{$date}.{$extension}";
            } else {
                $paths[] = "{$this->logDirectory}/{$fileName}.{$extension}";
            }
        }

        return $paths;
    }

    /**
     * @param array<int, string> $files
     */
    private function archiveFilesForDate(string $date, array $files): void
    {
        $ext = $this->config['archive']['extension'] ?? 'zip';

        $parts = explode('-', $date);
        $year = $parts[0] ?? date('Y');
        $month = $parts[1] ?? date('m');

        $archivePath = "{$this->archiveDirectory}/{$year}/{$month}";
        if (!is_dir($archivePath)) {
            mkdir($archivePath, 0777, true);
        }

        $zipPath = "{$archivePath}/{$date}.{$ext}";

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            return;
        }

        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }

        $zip->close();

        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function formatMessage(string $level, string $message, array $context, string $origin): string
    {
        $format = $this->config['log_format'] ?? '[{%datetime%}][{%level_name%}] {%message%}';

        $replace = [
            '{%datetime%}' => new DateTime()->format('Y-m-d H:i:s'),
            '{%level_name%}' => $level,
            '{%level_code%}' => (string) self::LEVELS[$level],
            '{%origin%}' => $origin,
            '{%message%}' => $message,
            '{%context%}' => $context ? (string) json_encode($context, JSON_UNESCAPED_UNICODE) : '',
        ];

        return str_replace(array_keys($replace), array_values($replace), $format);
    }
}