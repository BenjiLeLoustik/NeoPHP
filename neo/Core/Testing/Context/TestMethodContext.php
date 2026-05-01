<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Context;

class TestMethodContext
{
    public function __construct(
        public readonly string $name,
        public readonly array $cases = [],
        public readonly ?string $route = null,
        public readonly string $httpMethod = 'GET',
        public readonly array $dataset = [],
        public readonly bool $skip = false,
    ) {}
}