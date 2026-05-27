<?php

namespace Neo\Core\Cron\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Cron
{
    public function __construct(
        public readonly string $expression,
        public readonly string $description,
        public readonly string $timezone = 'UTC',
        public readonly bool $lock = false,
    ){}
}