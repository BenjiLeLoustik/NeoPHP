<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class RateLimit
{
    public int $maxAttempts;
    public int $decaySeconds;
    public string $message;

    public function __construct(
        int $maxAttempts = 60,
        int $decaySeconds = 60,
        string $message = 'Too many requests; please try again in a few moments.',
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
        $this->message = $message;
    }
}