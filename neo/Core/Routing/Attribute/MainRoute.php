<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class MainRoute
{
    public function __construct(
        public string $path,
        public string $name,
    ) {
    }
}
