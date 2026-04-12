<?php
declare(strict_types=1);

namespace Neo\Core\Event\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class AsListener
{
    public string $event;
    public int $priority;

    public function __construct(string $event, int $priority)
    {
        $this->event = $event;
        $this->priority = $priority;
    }
}