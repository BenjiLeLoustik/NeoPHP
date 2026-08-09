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

    /** @var array<string, string|null> */
    private array $collectorPackages = [];

    private float $startTime;
    private int $startMemory;
    private bool $collectorsRegistered = false;
    private ?\Throwable $bootError = null;

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

    public function addCollector(CollectorInterface $collector, ?string $packageName = null): void
    {
        $name = $collector->getName();
        $this->collectors[$name] = $collector;
        $this->collectorPackages[$name] = $packageName;
    }

    public function markCollectorsRegistered(): void
    {
        $this->collectorsRegistered = true;
    }

    public function areCollectorsRegistered(): bool
    {
        return $this->collectorsRegistered;
    }

    public function setBootError(\Throwable $e): void
    {
        $this->bootError = $e;
    }

    public function getBootError(): ?\Throwable
    {
        return $this->bootError;
    }

    public function hasBootError(): bool
    {
        return $this->bootError !== null;
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

    public function getCollectorPackage(string $name): ?string
    {
        return $this->collectorPackages[$name] ?? null;
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
                'package' => $this->collectorPackages[$name] ?? null,
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