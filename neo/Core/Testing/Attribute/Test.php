<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Test
{
    public string $type;

    /** @var list<array<string, mixed>> */
    public array $cases = [];

    public ?string $route = null;

    public string $httpMethod = 'GET';

    /** @var array<string, mixed> */
    public array $dataset = [];

    public bool $skip = false;

    public ?string $extends = null;

    public function __construct(
        string $type = 'auto',
        /** @param list<array<string, mixed>> $cases */
        array $cases = [],
        ?string $route = null,
        string $httpMethod = 'GET',
        /** @param array<string, mixed> $dataset */
        array $dataset = [],
        bool $skip = false,
        ?string $extends = null
    ) {
        $this->type = $type;
        $this->cases = $cases;
        $this->route = $route;
        $this->httpMethod = $httpMethod;
        $this->dataset = $dataset;
        $this->skip = $skip;
        $this->extends = $extends;
    }
}