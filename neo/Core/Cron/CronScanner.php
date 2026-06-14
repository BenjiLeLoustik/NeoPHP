<?php

namespace Neo\Core\Cron;

use Neo\Core\Cron\Attribute\Cron;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;

class CronScanner
{
    /**
     * @return list<array{class: class-string, method: string, expression: string, description: string, timezone: string, lock: bool}>
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

            $fqcn = $namespace !== ''
                ? $namespace . '\\' . $mClass[1]
                : $mClass[1];

            require_once $filePath;

            if (!class_exists($fqcn)) {
                continue;
            }

            $ref = new ReflectionClass($fqcn);

            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(Cron::class);

                if (empty($attributes)) {
                    continue;
                }

                $cron = $attributes[0]->newInstance();

                $jobs[] = [
                    'class' => $fqcn,
                    'method' => $method->getName(),
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