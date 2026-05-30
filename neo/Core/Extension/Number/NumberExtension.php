<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Number;

class NumberExtension
{

    public function format(int|float $number, int $decimals = 2, string $decimal = '.', string $thousands = ','): string
    {
        return number_format($number, $decimals, $decimal, $thousands);
    }

    public function currency(int|float $amount, string $symbol = '$', int $decimals = 2): string
    {
        return $symbol . $this->format($amount, $decimals);
    }

    public function percent(int|float $value, int|float $total, int $decimals = 2): string
    {
        if ($total == 0) return '0%';
        return $this->format(($value / $total) * 100, $decimals) . '%';
    }

    public function ordinal(int $number): string
    {
        $suffix = match (true) {
            $number % 100 >= 11 && $number % 100 <= 13 => 'th',
            $number % 10 === 1 => 'st',
            $number % 10 === 2 => 'nd',
            $number % 10 === 3 => 'rd',
            default => 'th',
        };

        return $number . $suffix;
    }

    public function humanSize(int $bytes, int $decimals = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return $this->format($bytes, $decimals) . ' ' . $units[$i];
    }


    public function clamp(int|float $value, int|float $min, int|float $max): int|float
    {
        return max($min, min($max, $value));
    }

    public function round(int|float $value, int $precision = 0): int|float
    {
        return round($value, $precision);
    }

    public function ceil(int|float $value): int
    {
        return (int) ceil($value);
    }

    public function floor(int|float $value): int
    {
        return (int) floor($value);
    }

    public function random(int $min = 0, int $max = PHP_INT_MAX): int
    {
        return random_int($min, $max);
    }

    public function randomFloat(float $min = 0.0, float $max = 1.0, int $decimals = 2): float
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), $decimals);
    }

    public function isBetween(int|float $value, int|float $min, int|float $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    public function isPositive(int|float $value): bool
    {
        return $value > 0;
    }

    public function isNegative(int|float $value): bool
    {
        return $value < 0;
    }

    public function isEven(int $value): bool
    {
        return $value % 2 === 0;
    }

    public function isOdd(int $value): bool
    {
        return $value % 2 !== 0;
    }

    public function toRoman(int $number): string
    {
        $map = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];

        $result = '';
        foreach ($map as $value => $symbol) {
            while ($number >= $value) {
                $result .= $symbol;
                $number -= $value;
            }
        }

        return $result;
    }

    public function fromRoman(string $roman): int
    {
        $map = ['I' => 1, 'V' => 5, 'X' => 10, 'L' => 50, 'C' => 100, 'D' => 500, 'M' => 1000];
        $result = 0;
        $prev = 0;

        foreach (array_reverse(str_split(strtoupper($roman))) as $char) {
            $value = $map[$char] ?? 0;
            $result += $value < $prev ? -$value : $value;
            $prev = $value;
        }

        return $result;
    }

    public function celsiusToFahrenheit(float $celsius): float
    {
        return round($celsius * 9 / 5 + 32, 2);
    }

    public function fahrenheitToCelsius(float $fahrenheit): float
    {
        return round(($fahrenheit - 32) * 5 / 9, 2);
    }

    public function kmToMiles(float $km): float
    {
        return round($km * 0.621371, 4);
    }

    public function milesToKm(float $miles): float
    {
        return round($miles * 1.60934, 4);
    }
}