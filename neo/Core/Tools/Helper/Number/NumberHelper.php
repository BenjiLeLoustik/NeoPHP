<?php

namespace Neo\Core\Tools\Helper\Number;

class NumberHelper
{
    public static function compact(int|float $number, int $precision = 1): string
    {
        $sign = $number < 0 ? '-' : '';
        $abs = abs($number);

        $units = [
            1_000_000_000 => 'B',
            1_000_000 => 'M',
            1_000 => 'K',
        ];

        foreach ($units as $threshold => $suffix) {
            if ($abs >= $threshold) {
                $divided = $abs / $threshold;
                $factor = 10 ** $precision;
                $truncated = floor($divided * $factor) / $factor;

                $formatted = number_format($truncated, $precision, '.', '');
                $formatted = rtrim(rtrim($formatted, '0'), '.');

                return $sign . $formatted . $suffix;
            }
        }

        return $sign . $abs;
    }

    public static function formatDecimal(int|float $number, int $precision = 1): string
    {
        return number_format((float)$number, $precision, '.', '');
    }
}