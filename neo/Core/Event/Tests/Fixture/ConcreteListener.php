<?php
declare(strict_types=1);

namespace Neo\Core\Event\Tests\Fixture;

use Neo\Core\Event\Interface\EventInterface;

final class ConcreteListener
{
    public bool $called = false;

    public function handle(EventInterface $event): void
    {
        $this->called = true;
    }
}