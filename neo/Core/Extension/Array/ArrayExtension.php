<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Array;

class ArrayExtension
{

    /**
     * @param array<array-key, mixed> $array
     */
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

    /**
     * @param array<array-key, mixed> $array
     */
    public function has(array $array, string $key): bool
    {
        return $this->get($array, $key, '__NOT_FOUND__') !== '__NOT_FOUND__';
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public function first(array $array, mixed $default = null): mixed
    {
        return empty($array) ? $default : array_values($array)[0];
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public function last(array $array, mixed $default = null): mixed
    {
        return empty($array) ? $default : array_values($array)[count($array) - 1];
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
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

    /**
     * @param array<array-key, mixed> $array
     * @return array<array-key, mixed>
     */
    public function pluck(array $array, string $key): array
    {
        return array_map(fn($item) => is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->$key ?? null) : null), $array);
    }

    /**
     * @template T
     * @param array<array-key, T> $array
     * @return list<T>
     */
    public function unique(array $array): array
    {
        return array_values(array_unique($array));
    }

    /**
     * @param array<array-key, mixed> $array
     * @return list<list<mixed>>
     */
    public function chunk(array $array, int $size): array
    {
        return array_chunk($array, $size);
    }

    /**
     * @template T
     * @param array<array-key, T> $array
     * @return list<T>
     */
    public function compact(array $array): array
    {
        return array_values(array_filter($array, fn($v) => $v !== null && $v !== '' && $v !== false));
    }

    /**
     * @param array<array-key, mixed> ...$arrays
     * @return list<list<mixed>>
     */
    public function zip(array ...$arrays): array
    {
        return array_map(null, ...$arrays);
    }

    /**
     * @param array<array-key, mixed> $array
     * @return array<string|int, mixed>
     */
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

    /**
     * @param array<array-key, mixed> $array
     * @return array<string|int, list<mixed>>
     */
    public function groupBy(array $array, string $key): array
    {
        $result = [];
        foreach ($array as $item) {
            $k = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            $result[$k][] = $item;
        }
        return $result;
    }

    /**
     * @template T
     * @param array<array-key, T> $array
     * @return list<T>
     */
    public function where(array $array, string $key, mixed $value): array
    {
        return array_values(array_filter($array, fn($item) => (is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null)) === $value));
    }

    /**
     * @template T
     * @param array<array-key, T> $array
     * @param array<array-key, mixed> $values
     * @return list<T>
     */
    public function whereIn(array $array, string $key, array $values): array
    {
        return array_values(array_filter($array, fn($item) => in_array(is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null), $values, true)));
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public function contains(array $array, mixed $value): bool
    {
        return in_array($value, $array, true);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public function search(array $array, string $key, mixed $value): mixed
    {
        foreach ($array as $item) {
            $v = is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null);
            if ($v === $value) return $item;
        }
        return null;
    }

    /**
     * @template T
     * @param array<array-key, T> $array
     * @return array<array-key, T>
     */
    public function sortBy(array $array, string $key, string $direction = 'asc'): array
    {
        usort($array, function ($a, $b) use ($key, $direction) {
            $va = is_array($a) ? ($a[$key] ?? null) : ($a->$key ?? null);
            $vb = is_array($b) ? ($b[$key] ?? null) : ($b->$key ?? null);
            return $direction === 'asc' ? $va <=> $vb : $vb <=> $va;
        });
        return $array;
    }

    /**
     * @template T
     * @param array<array-key, T> $array
     * @return array<array-key, T>
     */
    public function reverse(array $array): array
    {
        return array_reverse($array);
    }

    /**
     * @template T
     * @param array<array-key, T> $array
     * @return array<array-key, T>
     */
    public function shuffle(array $array): array
    {
        shuffle($array);
        return $array;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public function sum(array $array, ?string $key = null): int|float
    {
        if ($key !== null) {
            $array = $this->pluck($array, $key);
        }
        return array_sum($array);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public function avg(array $array, ?string $key = null): float
    {
        if (empty($array)) return 0;
        return $this->sum($array, $key) / count($array);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public function min(array $array, ?string $key = null): mixed
    {
        if ($key !== null) {
            $array = $this->pluck($array, $key);
        }
        return min($array);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public function max(array $array, ?string $key = null): mixed
    {
        if ($key !== null) {
            $array = $this->pluck($array, $key);
        }
        return max($array);
    }

    /**
     * @param array<array-key, mixed> $a
     * @param string|null $k
     * @return int
     */
    public function count(array $a, ?string $k = null): int
    {
        if ($k !== null) {
            $a = $this->pluck($a, $k);
        }
        return count($a);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @template TMapValue
     * @param array<TKey, TValue> $array
     * @param callable(TValue, TKey): TMapValue $callback
     * @return array<TKey, TMapValue>
     */
    public function map(array $array, callable $callback): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[$key] = $callback($value, $key);
        }
        return $result;
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey, TValue> $array
     * @param callable(TValue, TKey): bool $callback
     * @return array<TKey, TValue>
     */
    public function filter(array $array, callable $callback): array
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @template TValue
     * @param array<array-key, TValue> $array
     * @param callable(mixed, TValue): mixed $callback
     */
    public function reduce(array $array, callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($array, $callback, $initial);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey, TValue> $array
     * @param callable(TValue, TKey): void $callback
     */
    public function each(array $array, callable $callback): void
    {
        foreach ($array as $key => $item) {
            $callback($item, $key);
        }
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey, TValue> $array
     * @param array<array-key, mixed> $others
     * @return array<TKey, TValue>
     */
    public function diff(array $array, array $others): array
    {
        return array_diff($array, $others);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey, TValue> $array
     * @param array<array-key, mixed> $others
     * @return array<TKey, TValue>
     */
    public function intersect(array $array, array $others): array
    {
        return array_intersect($array, $others);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey, TValue> $array
     * @param array<array-key, TKey> $keys
     * @return array<TKey, TValue>
     */
    public function merge(array $array, array $keys): array
    {
        return array_merge($array, $keys);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey, TValue> $array
     * @param array<int, TKey> $keys
     * @return array<TKey, TValue>
     */
    public function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey, TValue> $array
     * @param array<int, TKey> $keys
     * @return array<TKey, TValue>
     */
    public function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }
}