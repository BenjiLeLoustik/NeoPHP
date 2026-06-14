<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Url;

class UrlExtension
{
    /**
     * @return array<string, string|int>
     */
    public function parse(string $url): array
    {
        return parse_url($url) ?: [];
    }

    public function scheme(string $url): string
    {
        return $this->parse($url)['scheme'] ?? '';
    }

    public function host(string $url): string
    {
        return $this->parse($url)['host'] ?? '';
    }

    public function path(string $url): string
    {
        return $this->parse($url)['path'] ?? '';
    }

    public function queryString(string $url): string
    {
        return $this->parse($url)['query'] ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    public function queryParams(string $url): array
    {
        $query = $this->queryString($url);
        parse_str($query, $params);
        return $params;
    }

    public function fragment(string $url): string
    {
        return $this->parse($url)['fragment'] ?? '';
    }

    /**
     * @param array<string, mixed> $parts
     */
    public function build(array $parts): string
    {
        $url = '';

        if (!empty($parts['scheme'])) {
            $url .= $parts['scheme'] . '://';
        }

        if (!empty($parts['host'])) {
            $url .= $parts['host'];
        }

        if (!empty($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        if (!empty($parts['path'])) {
            $url .= '/' . ltrim($parts['path'], '/');
        }

        if (!empty($parts['query'])) {
            $url .= '?' . (is_array($parts['query'])
                    ? http_build_query($parts['query'])
                    : $parts['query']);
        }

        if (!empty($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function addQueryParams(string $url, array $params): string
    {
        $existing = $this->queryParams($url);
        $merged = array_merge($existing, $params);
        $base = strtok($url, '?');
        return $base . '?' . http_build_query($merged);
    }

    public function removeQueryParam(string $url, string $key): string
    {
        $params = $this->queryParams($url);
        unset($params[$key]);
        $base = strtok($url, '?');
        return empty($params) ? $base : $base . '?' . http_build_query($params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function buildQuery(array $params): string
    {
        return http_build_query($params);
    }

    public function isAbsolute(string $url): bool
    {
        return (bool) preg_match('/^https?:\/\//i', $url);
    }

    public function isRelative(string $url): bool
    {
        return !$this->isAbsolute($url);
    }

    public function isValid(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function isSameDomain(string $url1, string $url2): bool
    {
        return $this->host($url1) === $this->host($url2);
    }

    public function slugifyPath(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        $slugified = array_map(fn($s) => preg_replace('/[^a-z0-9\-]/', '-', strtolower($s)), $segments);
        return '/' . implode('/', array_filter($slugified));
    }

    public function encode(string $url): string
    {
        return urlencode($url);
    }

    public function decode(string $url): string
    {
        return urldecode($url);
    }

    public function encodeComponent(string $component): string
    {
        return rawurlencode($component);
    }

    public function normalize(string $url): string
    {
        $parts = $this->parse($url);

        if (!empty($parts['path'])) {
            $parts['path'] = '/' . ltrim($parts['path'], '/');
        }

        if (!empty($parts['scheme'])) {
            $parts['scheme'] = strtolower($parts['scheme']);
        }

        if (!empty($parts['host'])) {
            $parts['host'] = strtolower($parts['host']);
        }

        return $this->build($parts);
    }
}