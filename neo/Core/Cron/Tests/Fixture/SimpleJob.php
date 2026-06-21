<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Tests\Fixture;

final class SimpleJob
{
    /**
     * @var list<string>
     */
    public static array $calls = [];

    public function run(): void { self::$calls[] = 'simple'; }
}