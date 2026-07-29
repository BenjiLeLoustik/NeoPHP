<?php

namespace Neo\Core\Database\Seeder\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Seeder
{
    public function __construct(
        public int $order = 0,
        public string $group = 'reference'
    ) {
    }
}