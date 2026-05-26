<?php
declare(strict_types=1);

namespace Neo\Core\Utils;

class ArrayExtension
{

    public function get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    public function has(array $array, string $key): bool
    {
        return $this->get($array, $key, '__NOT_FOUND__') !== '__NOT_FOUND__';
    }

    public function first(array $array, mixed $default = null): mixed
    {
        return empty($array) ? $default : array_values($array)[0];
    }

    public function last(array $array, mixed $default = null): mixed
    {
        return empty($array) ? $default : array_values($array)[count($array) - 1];
    }

    public function flatten(array $array, ?int $depth = null): array
    {
        $result = [];

        foreach ($array as $item) {
            if (is_array($item) && ($depth === null || $depth > 0)) {
                $result = array_merge($result, $this->flatten($item, $depth === null ? null : $depth - 1));
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    public function pluck(array $array, string $key): array
    {
        return array_map(fn($item) => is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null), $array);
    }

    public function unique(array $array): array
    {
        return array_values(array_unique($array));
    }

    public function chunk(array $array, int $size): array
    {
        return array_chunk($array, $size);
    }

    public function compact(array $array): array
    {
        return array_values(array_filter($array, fn($v) => $v !== null && $v !== '' && $v !== false));
    }

    public function zip(array ...$arrays): array
    {
        return array_map(null, ...$arrays);
    }

    public function keyBy(array $array, string $key): array
    {
        $result = [];
        foreach ($array as $item) {
            $k = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            if ($k !== null) {
                $result[$k] = $item;
            }
        }
        return $result;
    }

    public function groupBy(array $array, string $key): array
    {
        $result = [];
        foreach ($array as $item) {
            $k = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            $result[$k][] = $item;
        }
        return $result;
    }

    public function where(array $array, string $key, mixed $value): array
    {
        return array_values(array_filter($array, fn($item) => (is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null)) === $value));
    }

    public function whereIn(array $array, string $key, array $values): array
    {
        return array_values(array_filter($array, fn($item) => in_array(is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null), $values, true)));
    }

    public function contains(array $array, mixed $value): bool
    {
        return in_array($value, $array, true);
    }

    public function search(array $array, string $key, mixed $value): mixed
    {
        foreach ($array as $item) {
            $v = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            if ($v === $value) return $item;
        }
        return null;
    }

    public function sortBy(array $array, string $key, string $direction = 'asc'): array
    {
        usort($array, function ($a, $b) use ($key, $direction) {
            $va = is_array($a) ? ($a[$key] ?? null) : ($a->$key ?? null);
            $vb = is_array($b) ? ($b[$key] ?? null) : ($b->$key ?? null);
            return $direction === 'asc' ? $va <=> $vb : $vb <=> $va;
        });
        return $array;
    }

    public function reverse(array $array): array
    {
        return array_reverse($array);
    }

    public function shuffle(array $array): array
    {
        shuffle($array);
        return $array;
    }

    public function sum(array $array, ?string $key = null): int|float
    {
        if ($key !== null) {
            $array = $this->pluck($array, $key);
        }
        return array_sum($array);
    }

    public function avg(array $array, ?string $key = null): float
    {
        if (empty($array)) return 0;
        return $this->sum($array, $key) / count($array);
    }

    public function min(array $array, ?string $key = null): mixed
    {
        if ($key !== null) {
            $array = $this->pluck($array, $key);
        }
        return min($array);
    }

    public function max(array $array, ?string $key = null): mixed
    {
        if ($key !== null) {
            $array = $this->pluck($array, $key);
        }
        return max($array);
    }

    public function count(array $array, ?string $key = null): int
    {
        if ($key !== null) {
            return count(array_filter($this->pluck($array, $key)));
        }
        return count($array);
    }

    public function map(array $array, callable $callback): array
    {
        return array_map($callback, $array);
    }

    public function filter(array $array, ?callable $callback = null): array
    {
        return array_values($callback ? array_filter($array, $callback) : array_filter($array));
    }

    public function reduce(array $array, callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($array, $callback, $initial);
    }

    public function each(array $array, callable $callback): void
    {
        foreach ($array as $key => $value) {
            $callback($value, $key);
        }
    }

    public function diff(array $array, array ...$others): array
    {
        return array_values(array_diff($array, ...$others));
    }

    public function intersect(array $array, array ...$others): array
    {
        return array_values(array_intersect($array, ...$others));
    }

    public function merge(array ...$arrays): array
    {
        return array_merge(...$arrays);
    }

    public function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    public function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }
}