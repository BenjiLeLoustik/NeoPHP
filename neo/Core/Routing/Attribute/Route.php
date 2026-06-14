<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public string $path;

    public string $name;

    /** @var array<int, string> */
    public array $methods;

    /** @var array<string, string> */
    public array $requirements;

    /**
     * @param array<int, string> $methods
     * @param array<string, string> $requirements
     */
    public function __construct(
        string $path,
        string $name = '',
        array $methods = ['GET'],
        array $requirements = []
    ) {
        $this->path = $path;
        $this->name = $name;
        $this->methods = $methods;
        $this->requirements = $requirements;
    }
}
