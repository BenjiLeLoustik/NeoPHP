<?php
declare(strict_types = 1);

namespace Neo\Core\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Maintenance
{

    public function __construct(
        public string $message = 'Under maintenance'
    ) {
    }
}