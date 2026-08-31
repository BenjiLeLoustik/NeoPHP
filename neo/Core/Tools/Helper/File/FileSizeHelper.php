<?php

namespace Neo\Core\Tools\Helper\File;

class FileSizeHelper
{
    public const array UNITS = [
        'B' => 0,
        'KB' => 1,
        'MB' => 2,
        'GB' => 3,
        'TB' => 4,
        'PB' => 5,
        'EB' => 6
    ];

    public static function format(int $bytes, int $precisions = 1): string
    {
        $keys = array_keys(self::UNITS);
        $i = 0;
        $size = $bytes;

        while ($size >= 1024 && $i < count($keys) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, $precisions) . ' ' . $keys[$i];
    }

    public static function parse(string $formatted): int
    {
        if (!preg_match('/^([\d.]+)\s*([A-Za-z]+)$/', trim($formatted), $matches)) {
            return 0;
        }

        $value = (float)$matches[1];
        $unit = strtoupper($matches[2]);

        if (!isset(self::UNITS[$unit])) {
            return 0;
        }

        return (int)round($value * (1024 ** self::UNITS[$unit]));
    }
}