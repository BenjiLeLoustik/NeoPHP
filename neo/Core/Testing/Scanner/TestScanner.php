<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Scanner;

use Neo\Core\Testing\Attribute\Test;
use Neo\Core\Testing\Context\TestClassContext;
use Neo\Core\Testing\Context\TestMethodContext;
use Neo\Core\Testing\Enum\TestType;
use ReflectionClass;
use ReflectionMethod;

class TestScanner
{
    public function scan(string $srcPath): array
    {
        $contexts = [];

        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcPath)
        );

        foreach ($rii as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();
            $src = file_get_contents($filePath);
            if ($src === false) continue;

            if (!str_contains($src, '#[Test') && !str_contains($src, 'Test(')) {
                continue;
            }

            $namespace = '';
            $shortName = '';

            if (preg_match('/namespace\s+([^;]+);/i', $src, $m)) {
                $namespace = trim($m[1]);
            }
            if (preg_match('/class\s+([A-Za-z0-9_]+)/i', $src, $mc)) {
                $shortName = $mc[1];
            }

            if ($shortName === '') continue;

            $fqcn = $namespace !== ''
                ? $namespace . '\\' . $shortName
                : $shortName;

            require_once $filePath;

            if (!class_exists($fqcn)) continue;

            try {
                $refClass = new ReflectionClass($fqcn);
            } catch (\ReflectionException) {
                continue;
            }

            $classAttr = $refClass->getAttributes(Test::class)[0] ?? null;
            $methodCtxs = $this->scanMethods($refClass);

            if ($classAttr === null && empty($methodCtxs)) {
                continue;
            }

            $classTest = $classAttr?->newInstance();

            $type = TestType::Auto;

            if ($classTest !== null && $classTest->type !== 'auto') {
                $type = TestType::from($classTest->type);
            } elseif ($classTest !== null) {
                $type = TestType::fromNamespace($fqcn);
            } else {
                $type = TestType::fromNamespace($fqcn);
            }

            $contexts[] = new TestClassContext(
                fqcn: $fqcn,
                shortName: $shortName,
                namespace: $namespace,
                type: $type,
                methods: $methodCtxs,
                cases: $classTest?->cases ?? [],
                dataset: $classTest?->dataset ?? [],
                skip: $classTest?->skip ?? false,
                customExtends: $classTest?->extends,
            );
        }

        return $contexts;
    }

    private function scanMethods(ReflectionClass $refClass): array
    {
        $methods = [];

        foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attrs = $method->getAttributes(Test::class);

            if (empty($attrs)) continue;

            $test = $attrs[0]->newInstance();

            if ($test->skip) continue;

            $methods[] = new TestMethodContext(
                name: $method->getName(),
                cases: $test->cases,
                route: $test->route,
                httpMethod: $test->httpMethod,
                dataset: $test->dataset,
                skip: $test->skip,
            );
        }

        return $methods;
    }
}