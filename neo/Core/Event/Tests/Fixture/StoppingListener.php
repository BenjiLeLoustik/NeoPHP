<?php
declare(strict_types=1);

namespace Neo\Core\Event\Tests\Fixture;

use Neo\Core\Event\Interface\EventInterface;

final class StoppingListener
{
    public function handle(EventInterface $event): void
    {
        $event->stopPropagation();
    }
}