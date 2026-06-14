<?php
declare(strict_types=1);

namespace Neo\Core\Event\Contract;

interface EventSubscriberInterface
{
    /**
     * @return array<class-string, string|array{0: string, 1?: int}>
     */
    public static function getSubscribedEvents(): array;
}