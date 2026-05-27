<?php

namespace Neo\Core\Cron;

use DateTime;
use DateTimeZone;
use Neo\Core\Cron\Exception\CronException;
use Neo\Core\DI\Container;
use Neo\Core\Utils\Logger;
use Throwable;

class CronRunner
{
    public function __construct(
        private Container $container
    )
    {}

    public function run(array $jobs): void
    {
        foreach ($jobs as $job) {
            if (!$this->isDue($job['expression'], $job['timezone'])) {
                continue;
            }

            $lockFile = null;

            if ($job['lock']) {
                $lockFile = sys_get_temp_dir() . '/neo_cron_' . md5($job['class'] . $job['method']) . '.lock';

                if (file_exists($lockFile)) {
                    $this->log('warning', sprintf(
                        "Cron '%s::%s' is already running (lock file exists), skipping.",
                        $job['class'],
                        $job['method']
                    ));
                    continue;
                }

                touch($lockFile);
            }

            try {
                $instance = $this->container->get($job['class']);
                $method = $job['method'];
                $instance->$method();

                $this->log('info', sprintf(
                    "Cron '%s::%s' executed successfully.",
                    $job['class'],
                    $job['method']
                ));
            } catch (Throwable $e) {
                $this->log('error', sprintf(
                    "Cron '%s::%s' failed: %s",
                    $job['class'],
                    $job['method'],
                    $e->getMessage()
                ));
            } finally {
                if ($lockFile && file_exists($lockFile)) {
                    unlink($lockFile);
                }
            }
        }
    }

    private function isDue(string $expression, string $timezone): bool
    {
        $parts = explode(' ', trim($expression));

        if (count($parts) !== 5) {
            throw new CronException(
                title: 'Invalid Cron Expression',
                message: sprintf("Cron expression '%s' is invalid. Expected 5 parts.", $expression),
                code: 500
            );
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        $tz = new DateTimeZone($timezone);
        $now = new DateTime('now', $tz);

        return $this->matchesPart($minute, (int) $now->format('i'))
            && $this->matchesPart($hour, (int) $now->format('G'))
            && $this->matchesPart($day, (int) $now->format('j'))
            && $this->matchesPart($month, (int) $now->format('n'))
            && $this->matchesPart($weekday, (int) $now->format('w'));
    }

    private function matchesPart(string $part, int $current): bool
    {
        if ($part === '*') return true;

        if (str_starts_with($part, '*/')) {
            $step = (int) substr($part, 2);
            return $step > 0 && $current % $step === 0;
        }

        if (str_contains($part, '-')) {
            [$from, $to] = explode('-', $part);
            return $current >= (int) $from && $current <= (int) $to;
        }

        if (str_contains($part, ',')) {
            return in_array($current, array_map('intval', explode(',', $part)), true);
        }

        return (int) $part === $current;
    }

    private function log(string $level, string $message): void
    {
        try {
            $this->container->get(Logger::class)->$level($message, [], 'Cron');
        } catch (\Throwable) {}
    }
}