<?php
declare(strict_types=1);

namespace Neo\Core\Event\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class AsListener
{
    public function __construct(
        public string $event,
        public int $priority,
    ) {
    }
}