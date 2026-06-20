<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Scanner;

use Neo\Core\Testing\Attribute\Test;
use Neo\Core\Testing\Context\TestClassContext;
use Neo\Core\Testing\Context\TestMethodContext;
use Neo\Core\Testing\Enum\TestType;
use Neo\Core\Utils\Scanner\AttributeScanner;
use ReflectionMethod;

class TestScanner
{
    /**
     * @return array<int, TestClassContext>
     */
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

            $fqcn = $namespace !== '' ? $namespace . '\\' . $shortName : $shortName;

            require_once $filePath;

            if (!class_exists($fqcn)) continue;

            $results = new AttributeScanner($fqcn)
                ->onClass()
                ->onMethods(ReflectionMethod::IS_PUBLIC)
                ->withAttribute(Test::class)
                ->scan();

            $classTest  = null;
            $methodCtxs = [];

            foreach ($results as $entry) {
                /** @var Test $test */
                $test = $entry['attribute'];

                if ($entry['type'] === 'class') {
                    $classTest = $test;
                } elseif ($entry['type'] === 'method') {
                    if ($test->skip) continue;

                    /** @var ReflectionMethod $refMethod */
                    $refMethod = $entry['reflection'];
                    $methodCtxs[] = new TestMethodContext(
                        name: $refMethod->getName(),
                        cases: $test->cases,
                        route: $test->route,
                        httpMethod: $test->httpMethod,
                        dataset: $test->dataset,
                        skip: $test->skip,
                    );
                }
            }

            if ($classTest === null && empty($methodCtxs)) {
                continue;
            }

            $type = match (true) {
                $classTest !== null && $classTest->type !== 'auto' => TestType::from($classTest->type),
                default => TestType::fromNamespace($fqcn),
            };

            $contexts[] = new TestClassContext(
                fqcn: $fqcn,
                shortName: $shortName,
                namespace: $namespace,
                type: $type,
                methods: $methodCtxs,
                cases: $classTest->cases ?? [],
                dataset: $classTest->dataset ?? [],
                skip: $classTest->skip ?? false,
                customExtends: $classTest->extends ?? null,
            );
        }

        return $contexts;
    }

}