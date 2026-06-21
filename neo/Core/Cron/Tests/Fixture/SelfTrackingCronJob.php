<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Tests\Fixture;

final class SelfTrackingCronJob
{
    public static bool $called = false;
    public function execute(): void { self::$called = true; }
}