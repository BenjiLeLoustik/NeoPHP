<?php

namespace Neo\Core\Cron;

use Neo\Core\Cron\Attribute\Cron;
use Neo\Core\Utils\Scanner\AttributeScanner;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionException;
use ReflectionMethod;

class CronScanner
{
    /**
     * @return list<array{class: class-string, method: string, expression: string, description: string, timezone: string, lock: bool}>
     * @throws ReflectionException
     */
    public function scan(string $cronsPath): array
    {
        $jobs = [];

        if (!is_dir($cronsPath)) {
            return $jobs;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cronsPath),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();
            $src = file_get_contents($filePath);
            if ($src === false) {
                continue;
            }

            $namespace = '';
            if (preg_match('/namespace\s+([^;]+);/i', $src, $m)) {
                $namespace = trim($m[1]);
            }

            if (!preg_match('/class\s+([A-Za-z0-9_]+)/i', $src, $mClass)) {
                continue;
            }

            $fqcn = $namespace !== '' ? $namespace . '\\' . $mClass[1] : $mClass[1];

            require_once $filePath;

            if (!class_exists($fqcn)) {
                continue;
            }

            $results = new AttributeScanner($fqcn)
                ->onMethods(ReflectionMethod::IS_PUBLIC)
                ->withAttribute(Cron::class)
                ->scan();

            foreach ($results as $entry) {
                /** @var Cron $cron */
                $cron = $entry['attribute'];
                /** @var ReflectionMethod $refMethod */
                $refMethod = $entry['reflection'];

                $jobs[] = [
                    'class' => $fqcn,
                    'method' => $refMethod->getName(),
                    'expression' => $cron->expression,
                    'description' => $cron->description,
                    'timezone' => $cron->timezone,
                    'lock' => $cron->lock,
                ];
            }
        }

        return $jobs;
    }
}