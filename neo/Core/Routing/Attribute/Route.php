<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{

    /**
     * @param array<int, string> $methods
     * @param array<string, string> $requirements
     */
    public function __construct(
        public string $path,
        public string $name = '',
        public array $methods = ['GET'],
        public array $requirements = []
    ) {
    }
}
