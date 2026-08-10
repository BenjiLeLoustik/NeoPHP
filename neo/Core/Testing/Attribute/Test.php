<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Test
{
    /**
     * @param string $type
     * @param list<array<string, mixed>> $cases
     * @param string|null $route
     * @param string $httpMethod
     * @param array<string, mixed> $dataset
     * @param bool $skip
     * @param string|null $extends
     */
    public function __construct(
        public string $type = 'auto',
        public array $cases = [],
        public ?string $route = null,
        public string $httpMethod = 'GET',
        public array $dataset = [],
        public bool $skip = false,
        public ?string $extends = null
    ) {
    }
}