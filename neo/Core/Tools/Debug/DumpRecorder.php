<?php

declare(strict_types=1);

namespace Neo\Core\Tools\Debug;

final class DumpRecorder
{
    /** @var list<array{html: string, caller: string|null}> */
    private static array $dumps = [];

    public static function record(string $html, ?string $caller): void
    {
        self::$dumps[] = ['html' => $html, 'caller' => $caller];
    }

    /**
     * @return list<array{html: string, caller: string|null}>
     */
    public static function getDumps(): array
    {
        return self::$dumps;
    }
}