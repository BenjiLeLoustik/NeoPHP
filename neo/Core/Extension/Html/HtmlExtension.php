<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Html;

class HtmlExtension
{
    public function escape(string $value, string $encoding = 'UTF-8'): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, $encoding);
    }

    public function unescape(string $value): string
    {
        return htmlspecialchars_decode($value, ENT_QUOTES);
    }

    public function strip(string $html, string $allowedTags = ''): string
    {
        return strip_tags($html, $allowedTags);
    }

    public function truncate(string $html, int $limit, string $suffix = '...'): string
    {
        $text = $this->strip($html);

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit)) . $suffix;
    }

    public function excerpt(string $html, string $query, int $radius = 100, string $suffix = '...'): string
    {
        $text     = $this->strip($html);
        $position = mb_stripos($text, $query);

        if ($position === false) {
            return mb_substr($text, 0, $radius * 2) . $suffix;
        }

        $start  = max(0, $position - $radius);
        $length = mb_strlen($query) + ($radius * 2);
        $slice  = mb_substr($text, $start, $length);

        return ($start > 0 ? $suffix : '') . trim($slice) . $suffix;
    }

    public function nl2br(string $value): string
    {
        return nl2br($this->escape($value));
    }

    public function toText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        return trim($this->strip($text ?? ''));
    }

    public function minify(string $html): string
    {
        $html = preg_replace('/<!--(?!\[if).*?-->/s', '', $html);
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/>\s+</', '><', $html);
        return trim($html ?? '');
    }

    public function tag(string $tag, string $content, array $attributes = []): string
    {
        $attrs = '';
        foreach ($attributes as $key => $value) {
            $attrs .= sprintf(' %s="%s"', $key, $this->escape((string) $value));
        }

        return sprintf('<%s%s>%s</%s>', $tag, $attrs, $content, $tag);
    }

    public function selfClosingTag(string $tag, array $attributes = []): string
    {
        $attrs = '';
        foreach ($attributes as $key => $value) {
            $attrs .= sprintf(' %s="%s"', $key, $this->escape((string) $value));
        }

        return sprintf('<%s%s />', $tag, $attrs);
    }
}