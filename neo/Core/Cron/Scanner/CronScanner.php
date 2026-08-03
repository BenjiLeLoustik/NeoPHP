<?php

namespace Neo\Core\Cron\Scanner;

use Neo\Core\Cron\Attribute\Cron;
use Neo\Core\Utils\Scanner\ScannerAttributeManager;
use Neo\Core\Utils\Scanner\ScannerFileManager;
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

        $results = new ScannerFileManager()
            ->paths([$cronsPath])
            ->scan();

        foreach ($results as $result) {
            $fqcn = $result->getFqcn();

            if (!class_exists($fqcn)) {
                continue;
            }

            $attrResults = new ScannerAttributeManager($fqcn)
                ->onMethods(ReflectionMethod::IS_PUBLIC)
                ->withAttribute(Cron::class)
                ->scan();

            foreach ($attrResults as $entry) {
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