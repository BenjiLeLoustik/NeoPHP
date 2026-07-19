<?php
declare(strict_types=1);

namespace Neo\Core\Profiler;

use Neo\Core\Profiler\Interface\CollectorInterface;

class ProfilerManager
{
    private static ?self $instance = null;

    /** @var array<string, CollectorInterface> */
    private array $collectors = [];

    private float $startTime;
    private int $startMemory;

    private function __construct()
    {
        $this->startTime = defined('NEO_START_TIME')
            ? NEO_START_TIME
            : microtime(true);

        $this->startMemory = defined('NEO_START_MEMORY')
            ? NEO_START_MEMORY
            : memory_get_usage(true);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function addCollector(CollectorInterface $collector): void
    {
        $this->collectors[$collector->getName()] = $collector;
    }

    public function getCollector(string $name): ?CollectorInterface
    {
        return $this->collectors[$name] ?? null;
    }

    /**
     * @return array<string, CollectorInterface>
     */
    public function getCollectors(): array
    {
        return $this->collectors;
    }

    public function getStartTime(): float
    {
        return $this->startTime;
    }

    public function getStartMemory(): int
    {
        return $this->startMemory;
    }

    public function getTotalDuration(): float
    {
        return (microtime(true) - $this->startTime) * 1000;
    }

    public function getPeakMemory(): int
    {
        return memory_get_peak_usage(true);
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $data = [
            'duration' => round($this->getTotalDuration(), 2),
            'memory' => $this->getPeakMemory(),
        ];

        foreach ($this->collectors as $name => $collector) {
            $data[$name] = $collector->collect();
        }

        return $data;
    }
}