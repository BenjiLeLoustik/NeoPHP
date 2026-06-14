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
        string $type = 'auto',
        array $cases = [],
        ?string $route = null,
        string $httpMethod = 'GET',
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