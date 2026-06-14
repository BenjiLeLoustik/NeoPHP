<?php
declare(strict_types=1);

namespace Neo\Core\Extension\String;

use Neo\Core\DI\Container;
use Neo\Core\View\View;

class StringExtension
{

    public function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        return empty($text) ? 'n-a' : $text;
    }

    public function camelCase(string $text): string
    {
        $text = preg_replace('/[\s_\-]+/', ' ', $text);
        $text = ucwords(strtolower($text));
        return lcfirst(str_replace(' ', '', $text));
    }

    public function pascalCase(string $text): string
    {
        $text = preg_replace('/[\s_\-]+/', ' ', $text);
        return str_replace(' ', '', ucwords(strtolower($text)));
    }

    public function snakeCase(string $text): string
    {
        $text = preg_replace('/[\s\-]+/', '_', $text);
        $text = preg_replace('/([a-z])([A-Z])/', '$1_$2', $text);
        return strtolower($text);
    }

    public function kebabCase(string $text): string
    {
        $text = preg_replace('/[\s_]+/', '-', $text);
        $text = preg_replace('/([a-z])([A-Z])/', '$1-$2', $text);
        return strtolower($text);
    }

    public function titleCase(string $text): string
    {
        return ucwords(strtolower($text));
    }

    public function truncate(string $text, int $length, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length - mb_strlen($suffix))) . $suffix;
    }

    public function truncateWords(string $text, int $words, string $suffix = '...'): string
    {
        $parts = preg_split('/\s+/', trim($text));

        if (count($parts) <= $words) {
            return $text;
        }

        return implode(' ', array_slice($parts, 0, $words)) . $suffix;
    }

    public function excerpt(string $text, string $keyword, int $radius = 50): string
    {
        $pos = mb_stripos($text, $keyword);

        if ($pos === false) {
            return $this->truncate($text, $radius * 2);
        }

        $start = max(0, $pos - $radius);
        $length = mb_strlen($keyword) + $radius * 2;
        $excerpt = mb_substr($text, $start, $length);

        if ($start > 0) {
            $excerpt = '...' . $excerpt;
        }

        if ($start + $length < mb_strlen($text)) {
            $excerpt .= '...';
        }

        return $excerpt;
    }

    public function padLeft(string $text, int $length, string $pad = ' '): string
    {
        return str_pad($text, $length, $pad, STR_PAD_LEFT);
    }

    public function padRight(string $text, int $length, string $pad = ' '): string
    {
        return str_pad($text, $length, $pad, STR_PAD_RIGHT);
    }

    public function padBoth(string $text, int $length, string $pad = ' '): string
    {
        return str_pad($text, $length, $pad, STR_PAD_BOTH);
    }

    public function wrap(string $text, int $width, string $break = "\n"): string
    {
        return wordwrap($text, $width, $break, true);
    }

    public function repeat(string $text, int $times, string $separator = ''): string
    {
        return implode($separator, array_fill(0, $times, $text));
    }

    public function contains(string $text, string $needle): bool
    {
        return str_contains($text, $needle);
    }

    public function startsWith(string $text, string $prefix): bool
    {
        return str_starts_with($text, $prefix);
    }

    public function endsWith(string $text, string $suffix): bool
    {
        return str_ends_with($text, $suffix);
    }

    /**
     * @param string|array<array-key, string> $search
     * @param string|array<array-key, string> $replace
     */
    public function replace(string $text, string|array $search, string|array $replace): string
    {
        return str_replace($search, $replace, $text);
    }

    public function replaceFirst(string $text, string $search, string $replace): string
    {
        $pos = strpos($text, $search);

        if ($pos === false) {
            return $text;
        }

        return substr_replace($text, $replace, $pos, strlen($search));
    }

    public function replaceLast(string $text, string $search, string $replace): string
    {
        $pos = strrpos($text, $search);

        if ($pos === false) {
            return $text;
        }

        return substr_replace($text, $replace, $pos, strlen($search));
    }

    public function between(string $text, string $start, string $end): string
    {
        $posStart = strpos($text, $start);

        if ($posStart === false) {
            return '';
        }

        $posStart += strlen($start);
        $posEnd = strpos($text, $end, $posStart);

        if ($posEnd === false) {
            return '';
        }

        return substr($text, $posStart, $posEnd - $posStart);
    }

    public function sanitize(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param array<int, string> $allowed
     */
    public function stripTags(string $text, array $allowed = []): string
    {
        return strip_tags($text, $allowed);
    }

    public function stripSpaces(string $text): string
    {
        return preg_replace('/\s+/', ' ', trim($text));
    }

    public function stripAccents(string $text): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    }

    public function escapeHtml(string $text): string
    {
        return htmlentities($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function unescapeHtml(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function mask(string $text, int $visibleStart = 2, int $visibleEnd = 2, string $char = '*'): string
    {
        $length = mb_strlen($text);

        if ($length <= $visibleStart + $visibleEnd) {
            return str_repeat($char, $length);
        }

        $middle = str_repeat($char, $length - $visibleStart - $visibleEnd);
        return mb_substr($text, 0, $visibleStart) . $middle . mb_substr($text, -$visibleEnd);
    }
}