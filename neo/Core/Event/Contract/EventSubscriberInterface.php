<?php
declare(strict_types=1);

namespace Neo\Core\Event\Contract;

interface EventSubscriberInterface
{
    public static function getSubscribedEvents(): array;
}