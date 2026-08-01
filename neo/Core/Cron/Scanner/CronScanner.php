<?php

namespace Neo\Core\Cron\Scanner;

use Neo\Core\Cron\Attribute\Cron;
use Neo\Core\Utils\Scanner\ScannerAttributeManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionException;
use ReflectionMethod;

class CronScanner
{
    /**
     * @return list<array{
     *     class: class-string,
     *     method: string,
     *     expression: string,
     *     description: string,
     *     timezone: string,
     *     lock: bool
     * }>
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

            $results = new ScannerAttributeManager($fqcn)
                ->onMethods(ReflectionMethod::IS_PUBLIC)
                ->withAttribute(Cron::class)
                ->scan();

            foreach ($results as $entry) {
                $refMethod = $entry->getReflection();

                if (!$refMethod instanceof ReflectionMethod) {
                    continue;
                }

                /** @var Cron $cron */
                $cron = $entry->getAttribute();

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