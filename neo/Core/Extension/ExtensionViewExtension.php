<?php
declare(strict_types=1);

namespace Neo\Core\Extension;

use Neo\Core\Extension\Array\ArrayExtension;
use Neo\Core\Extension\Date\DateExtension;
use Neo\Core\Extension\File\FileExtension;
use Neo\Core\Extension\Html\HtmlExtension;
use Neo\Core\Extension\Json\JsonExtension;
use Neo\Core\Extension\Number\NumberExtension;
use Neo\Core\Extension\Path\PathExtension;
use Neo\Core\Extension\String\StringExtension;
use Neo\Core\Extension\Url\UrlExtension;
use Neo\Core\View\Interface\TwigExtensionInterface;

final class ExtensionViewExtension implements TwigExtensionInterface
{
    public function __construct(
        private readonly StringExtension $string,
        private readonly ArrayExtension $array,
        private readonly DateExtension $date,
        private readonly FileExtension $file,
        private readonly HtmlExtension $html,
        private readonly JsonExtension $json,
        private readonly NumberExtension $number,
        private readonly PathExtension $path,
        private readonly UrlExtension $url,
    ) {}

    public function getFunctions(): array
    {
        return [
            'array_get' => ['callable' => fn(array $a, string $k, mixed $d = null) => $this->array->get($a, $k, $d), 'options' => []],
            'array_has' => ['callable' => fn(array $a, string $k) => $this->array->has($a, $k), 'options' => []],
            'array_first' => ['callable' => fn(array $a, mixed $d = null) => $this->array->first($a, $d), 'options' => []],
            'array_last' => ['callable' => fn(array $a, mixed $d = null) => $this->array->last($a, $d), 'options' => []],
            'array_flatten' => ['callable' => fn(array $a, ?int $d = null) => $this->array->flatten($a, $d), 'options' => []],
            'array_pluck' => ['callable' => fn(array $a, string $k) => $this->array->pluck($a, $k), 'options' => []],
            'array_unique' => ['callable' => fn(array $a) => $this->array->unique($a), 'options' => []],
            'array_chunk' => ['callable' => fn(array $a, int $s) => $this->array->chunk($a, $s), 'options' => []],
            'array_compact' => ['callable' => fn(array $a) => $this->array->compact($a), 'options' => []],
            'array_key_by' => ['callable' => fn(array $a, string $k) => $this->array->keyBy($a, $k), 'options' => []],
            'array_group_by'=> ['callable' => fn(array $a, string $k) => $this->array->groupBy($a, $k), 'options' => []],
            'array_where' => ['callable' => fn(array $a, string $k, mixed $v) => $this->array->where($a, $k, $v), 'options' => []],
            'array_sort_by' => ['callable' => fn(array $a, string $k, string $dir = 'asc') => $this->array->sortBy($a, $k, $dir), 'options' => []],
            'array_sum' => ['callable' => fn(array $a, ?string $k = null) => $this->array->sum($a, $k), 'options' => []],
            'array_avg' => ['callable' => fn(array $a, ?string $k = null) => $this->array->avg($a, $k), 'options' => []],
            'array_min' => ['callable' => fn(array $a, ?string $k = null) => $this->array->min($a, $k), 'options' => []],
            'array_max' => ['callable' => fn(array $a, ?string $k = null) => $this->array->max($a, $k), 'options' => []],
            'array_count' => ['callable' => fn(array $a, ?string $k = null) => $this->array->count($a, $k), 'options' => []],

            'slugify' => ['callable' => fn(string $text) => $this->string->slugify($text), 'options' => []],
            'camel_case' => ['callable' => fn(string $text) => $this->string->camelCase($text), 'options' => []],
            'snake_case' => ['callable' => fn(string $text) => $this->string->snakeCase($text), 'options' => []],
            'pascal_case' => ['callable' => fn(string $text) => $this->string->pascalCase($text), 'options' => []],
            'truncate' => ['callable' => fn(string $text, int $length, string $suffix = '...') => $this->string->truncate($text, $length, $suffix), 'options' => []],
            'excerpt' => ['callable' => fn(string $text, string $keyword, int $radius = 50) => $this->string->excerpt($text, $keyword, $radius), 'options' => []],

            'number_format_ext' => ['callable' => fn(int|float $n, int $d = 2, string $dec = '.', string $thou = ',') => $this->number->format($n, $d, $dec, $thou), 'options' => []],
            'currency' => ['callable' => fn(int|float $amount, string $symbol = '$', int $decimals = 2) => $this->number->currency($amount, $symbol, $decimals), 'options' => []],
            'percent' => ['callable' => fn(int|float $value, int|float $total, int $decimals = 2) => $this->number->percent($value, $total, $decimals), 'options' => []],
            'ordinal' => ['callable' => fn(int $number) => $this->number->ordinal($number), 'options' => []],
            'human_size' => ['callable' => fn(int $bytes, int $decimals = 2) => $this->number->humanSize($bytes, $decimals), 'options' => []],
            'to_roman' => ['callable' => fn(int $number) => $this->number->toRoman($number), 'options' => []],

            'date_now' => ['callable' => fn(string $timezone = 'UTC') => $this->date->now($timezone), 'options' => []],
            'date_format' => ['callable' => fn(\DateTimeInterface|string $date, string $format = 'd/m/Y') => $this->date->format($date, $format), 'options' => []],
            'human_diff' => ['callable' => fn(\DateTimeInterface|string $date) => $this->date->humanDiff($date), 'options' => []],
            'date_age' => ['callable' => fn(\DateTimeInterface|string $birthdate) => $this->date->age($birthdate), 'options' => []],
            'is_past' => ['callable' => fn(\DateTimeInterface|string $date) => $this->date->isPast($date), 'options' => []],
            'is_future' => ['callable' => fn(\DateTimeInterface|string $date) => $this->date->isFuture($date), 'options' => []],
            'is_today' => ['callable' => fn(\DateTimeInterface|string $date) => $this->date->isToday($date), 'options' => []],

            'file_extension' => ['callable' => fn(string $path) => $this->file->extension($path), 'options' => []],
            'file_size' => ['callable' => fn(string $path) => $this->file->size($path), 'options' => []],
            'file_human_size' => ['callable' => fn(string $path, int $decimals = 2) => $this->file->humanSize($path, $decimals), 'options' => []],
            'file_exists' => ['callable' => fn(string $path) => $this->file->exists($path), 'options' => []],
            'file_mime' => ['callable' => fn(string $path) => $this->file->mimeType($path), 'options' => []],
            'is_image' => ['callable' => fn(string $path) => $this->file->isImage($path), 'options' => []],

            'html_escape' => ['callable' => fn(string $value) => $this->html->escape($value), 'options' => []],
            'html_strip' => ['callable' => fn(string $html, string $allowed = '') => $this->html->strip($html, $allowed), 'options' => []],
            'html_truncate' => ['callable' => fn(string $html, int $limit, string $suffix = '...') => $this->html->truncate($html, $limit, $suffix), 'options' => []],
            'html_to_text' => ['callable' => fn(string $html) => $this->html->toText($html), 'options' => []],
            'html_tag' => ['callable' => fn(string $tag, string $content, array $attrs = []) => $this->html->tag($tag, $content, $attrs), 'options' => ['is_safe' => ['html']]],

            'json_encode_ext' => ['callable' => fn(mixed $data, bool $pretty = false) => $this->json->encode($data, $pretty), 'options' => []],
            'json_decode_ext' => ['callable' => fn(string $json, bool $assoc = true) => $this->json->decode($json, $assoc), 'options' => []],
            'json_is_valid' => ['callable' => fn(string $json) => $this->json->isValid($json), 'options' => []],

            'url_is_valid' => ['callable' => fn(string $url) => $this->url->isValid($url), 'options' => []],
            'url_is_absolute' => ['callable' => fn(string $url) => $this->url->isAbsolute($url), 'options' => []],
            'url_host' => ['callable' => fn(string $url) => $this->url->host($url), 'options' => []],
            'url_params' => ['callable' => fn(string $url) => $this->url->queryParams($url), 'options' => []],
            'url_add_params' => ['callable' => fn(string $url, array $params) => $this->url->addQueryParams($url, $params), 'options' => []],

            'path_join' => ['callable' => fn(string ...$parts) => $this->path->join(...$parts), 'options' => []],
            'path_normalize' => ['callable' => fn(string $path) => $this->path->normalize($path), 'options' => []],
            'path_extension' => ['callable' => fn(string $path) => $this->path->extension($path), 'options' => []],
            'path_filename' => ['callable' => fn(string $path) => $this->path->filename($path), 'options' => []],
            'path_dirname' => ['callable' => fn(string $path) => $this->path->dirname($path), 'options' => []],
        ];
    }

    public function getFilters(): array
    {
        return [
            'array_pluck' => ['callable' => fn(array $a, string $k) => $this->array->pluck($a, $k), 'options' => []],
            'array_unique' => ['callable' => fn(array $a) => $this->array->unique($a), 'options' => []],
            'array_flatten' => ['callable' => fn(array $a, ?int $d = null) => $this->array->flatten($a, $d), 'options' => []],
            'array_compact' => ['callable' => fn(array $a) => $this->array->compact($a), 'options' => []],
            'array_reverse' => ['callable' => fn(array $a) => $this->array->reverse($a), 'options' => []],
            'array_sort_by' => ['callable' => fn(array $a, string $k, string $dir = 'asc') => $this->array->sortBy($a, $k, $dir), 'options' => []],
            'array_sum' => ['callable' => fn(array $a, ?string $k = null) => $this->array->sum($a, $k), 'options' => []],
            'array_count' => ['callable' => fn(array $a, ?string $k = null) => $this->array->count($a, $k), 'options' => []],

            'slugify' => ['callable' => fn(string $text) => $this->string->slugify($text), 'options' => []],
            'camel_case' => ['callable' => fn(string $text) => $this->string->camelCase($text), 'options' => []],
            'snake_case' => ['callable' => fn(string $text) => $this->string->snakeCase($text), 'options' => []],
            'pascal_case' => ['callable' => fn(string $text) => $this->string->pascalCase($text), 'options' => []],
            'truncate' => ['callable' => fn(string $text, int $length, string $suffix = '...') => $this->string->truncate($text, $length, $suffix), 'options' => []],
            'sanitize' => ['callable' => fn(string $text) => $this->string->sanitize($text), 'options' => []],

            'html_escape' => ['callable' => fn(string $value) => $this->html->escape($value), 'options' => []],
            'html_strip' => ['callable' => fn(string $html, string $allowed = '') => $this->html->strip($html, $allowed), 'options' => []],
            'nl2br' => ['callable' => fn(string $value) => $this->html->nl2br($value), 'options' => ['is_safe' => ['html']]],

            'date_format' => ['callable' => fn(\DateTimeInterface|string $date, string $format = 'd/m/Y') => $this->date->format($date, $format), 'options' => []],
            'human_diff' => ['callable' => fn(\DateTimeInterface|string $date) => $this->date->humanDiff($date), 'options' => []],
            'date_age' => ['callable' => fn(\DateTimeInterface|string $birthdate) => $this->date->age($birthdate), 'options' => []],

            'currency' => ['callable' => fn(int|float $amount, string $symbol = '$', int $decimals = 2) => $this->number->currency($amount, $symbol, $decimals), 'options' => []],
            'human_size' => ['callable' => fn(int $bytes, int $decimals = 2) => $this->number->humanSize($bytes, $decimals), 'options' => []],
            'ordinal' => ['callable' => fn(int $number) => $this->number->ordinal($number), 'options' => []],

            'json_encode_ext' => ['callable' => fn(mixed $data, bool $pretty = false) => $this->json->encode($data, $pretty), 'options' => []],

            'url_encode' => ['callable' => fn(string $url) => $this->url->encode($url), 'options' => []],
            'url_decode' => ['callable' => fn(string $url) => $this->url->decode($url), 'options' => []],
        ];
    }
}