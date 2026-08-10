<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Context;

use Neo\Core\Testing\Enum\TestType;

readonly class TestClassContext
{
    /**
     * @param array<string, mixed> $methods
     * @param array<int, mixed> $cases
     * @param array<string, mixed> $dataset
     */
    public function __construct(
        public string $fqcn,
        public string $shortName,
        public string $namespace,
        public TestType $type,
        /** @var array<int, TestMethodContext> */
        public array $methods,
        public array $cases = [],
        public array $dataset = [],
        public bool $skip = false,
        public ?string $customExtends = null,
    ){
    }
}