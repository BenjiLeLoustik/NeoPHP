<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Context;

use Neo\Core\Testing\Enum\TestType;

class TestClassContext
{
    public function __construct(
        public readonly string $fqcn,
        public readonly string $shortName,
        public readonly string $namespace,
        public readonly TestType $type,
        public readonly array $methods,
        public readonly array $cases      = [],
        public readonly array $dataset    = [],
        public readonly bool $skip       = false,
        public readonly ?string $customExtends = null,
    ){}
}