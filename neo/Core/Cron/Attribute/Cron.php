<?php

namespace Neo\Core\Cron\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Cron
{
    public function __construct(
        public string $expression,
        public string $description,
        public string $timezone = 'UTC',
        public bool $lock = false,
    ){}
}