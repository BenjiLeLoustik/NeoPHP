<?php

namespace Neo\Core\Module\Tests\Fixture;

class ModuleCallLog
{
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }
}