<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Tests\Fixture;

final class SecondJob
{
    public static bool $called = false;
    public function run(): void { self::$called = true; }
}