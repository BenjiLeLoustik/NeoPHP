<?php

declare(strict_types=1);

namespace Neo\Core\Profiler;

use Neo\Core\Profiler\Interface\CollectorInterface;

final class ProfilerManager
{
    private static ProfilerManager $instance;
    private static string $token;

    /** @var array<string, CollectorInterface> */
    private array $collectors = [];

    private float $startTime;
    private int $startMemory;

    private function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
            self::$token = bin2hex(random_bytes(6));
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = new self();
        self::$token = bin2hex(random_bytes(6));
    }

    public static function getToken(): string
    {
        return self::$token;
    }

    public function addCollector(CollectorInterface $collector): void
    {
        $this->collectors[$collector->getName()] = $collector;
    }

    /**
     * @return array<string, CollectorInterface>
     */
    public function getCollectors(): array
    {
        return $this->collectors;
    }

    public function getCollector(string $name): ?CollectorInterface
    {
        return $this->collectors[$name] ?? null;
    }

    public function getTotalDuration(): float
    {
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }

    public function getPeakMemory(): int
    {
        return memory_get_peak_usage(true);
    }

    /**
     * @return array<string, mixed>
     */
    public function export(?int $statusCode, string $method, string $path, string $ip): array
    {
        $collectorsExport = [];

        foreach ($this->collectors as $name => $collector) {
            $collectorsExport[$name] = [
                'in_toolbar' => $collector->inToolbar(),
                'in_profiler' => $collector->inProfiler(),
                'toolbar' => $collector->inToolbar() ? $collector->toolbarData() : null,
                'profiler' => $collector->inProfiler() ? $collector->profilerData() : null,
            ];
        }

        return [
            'token' => self::getToken(),
            'duration' => $this->getTotalDuration(),
            'memory' => $this->getPeakMemory(),
            'timestamp' => time(),
            'status_code' => $statusCode,
            'method' => $method,
            'path' => $path,
            'ip' => $ip,
            'collectors' => $collectorsExport,
        ];
    }
}