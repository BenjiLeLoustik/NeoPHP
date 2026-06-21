<?php

namespace Neo\Core\Module\Tests\Fixture;

class ModuleCallLog
{
    /**
     * @var list<string>
     */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }
}