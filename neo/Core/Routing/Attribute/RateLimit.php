<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class RateLimit
{
    public function __construct(
        public int $maxAttempts = 60,
        public int $decaySeconds = 60,
        public string $message = 'Too many requests; please try again in a few moments.',
    ) {
    }
}