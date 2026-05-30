<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Json;

class JsonExtension
{
    public function encode(mixed $data, bool $pretty = false): string|false
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) $flags |= JSON_PRETTY_PRINT;
        return json_encode($data, $flags);
    }

    public function decode(string $json, bool $assoc = true): mixed
    {
        return json_decode($json, $assoc);
    }

    public function isValid(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public function prettyPrint(string $json): string|false
    {
        $decoded = $this->decode($json);
        return $decoded !== null ? $this->encode($decoded, true) : false;
    }

    public function get(string $json, string $key, mixed $default = null): mixed
    {
        $data = $this->decode($json);
        if (!is_array($data)) return $default;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    public function set(string $json, string $key, mixed $value): string|false
    {
        $data = $this->decode($json) ?? [];
        $keys = explode('.', $key);
        $ref = &$data;

        foreach ($keys as $k) {
            if (!isset($ref[$k]) || !is_array($ref[$k])) {
                $ref[$k] = [];
            }
            $ref = &$ref[$k];
        }

        $ref = $value;
        return $this->encode($data);
    }

    public function merge(string $json1, string $json2, bool $deep = false): string|false
    {
        $a = $this->decode($json1) ?? [];
        $b = $this->decode($json2) ?? [];
        $merged = $deep ? array_replace_recursive($a, $b) : array_merge($a, $b);
        return $this->encode($merged);
    }

    public function diff(string $json1, string $json2): array
    {
        $a = $this->decode($json1) ?? [];
        $b = $this->decode($json2) ?? [];
        return array_diff_assoc($b, $a);
    }

    public function flatten(string $json, string $separator = '.'): array
    {
        $data = $this->decode($json) ?? [];
        return $this->flattenArray($data, '', $separator);
    }

    private function flattenArray(array $array, string $prefix, string $separator): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix !== '' ? $prefix . $separator . $key : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey, $separator));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    public function keys(string $json): array
    {
        $data = $this->decode($json);
        return is_array($data) ? array_keys($data) : [];
    }

    public function has(string $json, string $key): bool
    {
        return $this->get($json, $key, '__NOT_FOUND__') !== '__NOT_FOUND__';
    }

    public function toArray(string $json): array
    {
        return $this->decode($json) ?? [];
    }

    public function fromArray(array $data, bool $pretty = false): string|false
    {
        return $this->encode($data, $pretty);
    }
}