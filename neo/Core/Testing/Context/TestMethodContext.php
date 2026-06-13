<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Context;

readonly class TestMethodContext
{
    public function __construct(
        public string $name,
        public array $cases = [],
        public ?string $route = null,
        public string $httpMethod = 'GET',
        public array $dataset = [],
        public bool $skip = false,
    ) {}
}