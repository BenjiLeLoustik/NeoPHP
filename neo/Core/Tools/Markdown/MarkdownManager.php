<?php
declare(strict_types=1);

namespace Neo\Core\Tools\Markdown;

use Neo\Core\DI\Container;
use Neo\Core\Tools\Markdown\Exception\MarkdownException;

class MarkdownManager
{
    private Container $container;

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $cache = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws MarkdownException
     */
    public function blocks(string $input): array
    {
        if ($this->resolvePath($input) !== null) {
            return $this->parseFile($input);
        }

        if ($this->looksLikePath($input)) {
            $base = $this->basePath();

            throw new MarkdownException(
                title: 'Markdown Source Missing',
                message: sprintf(
                    "The markdown file '%s' could not be found (base path: '%s'). %s",
                    $input,
                    $base ?? '(none)',
                    $this->diagnosePath($base ?? '', $input)
                ),
                code: 404
            );
        }

        return $this->parse($input);
    }

    private function diagnosePath(string $base, string $relative): string
    {
        $segments = array_values(array_filter(
            explode('/', str_replace('\\', '/', $relative)),
            static fn (string $s): bool => $s !== '' && $s !== '.'
        ));

        $walked = rtrim(str_replace('\\', '/', $base), '/');

        foreach ($segments as $segment) {
            $next = $walked . '/' . $segment;

            if (!file_exists($next)) {
                $available = is_dir($walked)
                    ? array_values(array_filter(
                        scandir($walked) ?: [],
                        static fn (string $e): bool => $e !== '.' && $e !== '..'
                    ))
                    : [];

                return sprintf(
                    "Blocage sur '%s' : '%s' introuvable ici. Contenu de '%s' : [%s].",
                    $next,
                    $segment,
                    $walked,
                    $available === [] ? '(vide ou illisible)' : implode(', ', $available)
                );
            }

            $walked = $next;
        }

        return sprintf("Le chemin existe jusqu'à '%s' (est-ce bien un fichier ?).", $walked);
    }

    private function looksLikePath(string $input): bool
    {
        return !str_contains($input, "\n")
            && preg_match('/\.(md|markdown)$/i', trim($input)) === 1;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws MarkdownException
     */
    public function parseFile(string $path): array
    {
        $resolved = $this->resolvePath($path);

        if ($resolved === null) {
            throw new MarkdownException(
                title: 'Markdown Source Missing',
                message: sprintf("The markdown file '%s' could not be found.", $path),
                code: 404
            );
        }

        $memoKey = $resolved . ':' . (int) filemtime($resolved);

        if (isset($this->cache[$memoKey])) {
            return $this->cache[$memoKey];
        }

        $content = file_get_contents($resolved);

        if ($content === false) {
            throw new MarkdownException(
                title: 'Markdown Source Unreadable',
                message: sprintf("Unable to read markdown file '%s'.", $path),
                code: 500
            );
        }

        return $this->cache[$memoKey] = $this->parse($content);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $markdown): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        $blocks = [];
        $i = 0;
        $count = count($lines);

        while ($i < $count) {
            $line = $lines[$i];

            if (trim($line) === '') {
                $i++;
                continue;
            }

            if (preg_match('/^\s*```+\s*([\w+#-]*)\s*$/', $line, $m)) {
                $language = $m[1];
                $code = [];
                $i++;
                while ($i < $count && !preg_match('/^\s*```+\s*$/', $lines[$i])) {
                    $code[] = $lines[$i];
                    $i++;
                }
                $i++;
                $blocks[] = [
                    'type' => 'code',
                    'language' => $language,
                    'content' => implode("\n", $code),
                ];
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.*?)\s*#*\s*$/', $line, $m)) {
                $blocks[] = [
                    'type' => 'heading',
                    'level' => strlen($m[1]),
                    'text' => $m[2],
                    'slug' => $this->slug($m[2]),
                ];
                $i++;
                continue;
            }

            if (preg_match('/^\s*([-*_])(\s*\1){2,}\s*$/', $line)) {
                $blocks[] = ['type' => 'hr'];
                $i++;
                continue;
            }

            if (preg_match('/^\s*>\s?(.*)$/', $line, $m)) {
                $quote = [$m[1]];
                $i++;
                while ($i < $count && preg_match('/^\s*>\s?(.*)$/', $lines[$i], $mm)) {
                    $quote[] = $mm[1];
                    $i++;
                }
                $blocks[] = [
                    'type' => 'quote',
                    'content' => implode("\n", $quote),
                ];
                continue;
            }

            if (
                str_contains($line, '|')
                && isset($lines[$i + 1])
                && preg_match('/^\s*\|?[\s:|-]*-[\s:|-]*\|?\s*$/', $lines[$i + 1])
            ) {
                $header = $this->splitTableRow($line);
                $i += 2; // En-tête + séparateur.
                $rows = [];
                while (
                    $i < $count
                    && trim($lines[$i]) !== ''
                    && str_contains($lines[$i], '|')
                ) {
                    $rows[] = $this->splitTableRow($lines[$i]);
                    $i++;
                }
                $blocks[] = [
                    'type' => 'table',
                    'header' => $header,
                    'rows' => $rows,
                ];
                continue;
            }

            if (preg_match('/^\s*([-*+]|\d+[.)])\s+/', $line)) {
                $ordered = (bool) preg_match('/^\s*\d+[.)]\s+/', $line);
                $items = [];
                while (
                    $i < $count
                    && preg_match('/^\s*([-*+]|\d+[.)])\s+(.*)$/', $lines[$i], $mm)
                ) {
                    $items[] = $mm[2];
                    $i++;
                }
                $blocks[] = [
                    'type' => 'list',
                    'ordered' => $ordered,
                    'items' => $items,
                ];
                continue;
            }

            $paragraph = [];
            while (
                $i < $count
                && trim($lines[$i]) !== ''
                && !$this->startsNewBlock($lines[$i])
            ) {
                $paragraph[] = trim($lines[$i]);
                $i++;
            }
            $blocks[] = [
                'type' => 'paragraph',
                'text' => implode(' ', $paragraph),
            ];
        }

        return $blocks;
    }

    public function renderInline(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $text = preg_replace_callback(
            '/`([^`]+)`/',
            static fn (array $m): string => '<code>' . $m[1] . '</code>',
            $text
        ) ?? $text;

        $text = preg_replace('/!\[([^\]]*)\]\(([^)\s]+)\)/', '<img src="$2" alt="$1">', $text) ?? $text;

        $text = preg_replace('/\[([^\]]+)\]\(([^)\s]+)\)/', '<a href="$2">$1</a>', $text) ?? $text;

        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $text) ?? $text;

        $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/(?<![A-Za-z0-9])_([^_]+)_(?![A-Za-z0-9])/', '<em>$1</em>', $text) ?? $text;

        return $text;
    }

    private function resolvePath(string $path): ?string
    {
        if (is_file($path)) {
            return realpath($path) ?: $path;
        }

        $base = $this->basePath();

        if ($base !== null) {
            $rooted = $base . '/' . ltrim($path, '/');

            if (is_file($rooted)) {
                return realpath($rooted) ?: $rooted;
            }
        }

        return null;
    }

    private function basePath(): ?string
    {
        if (defined('ROOT_DIR')) {
            return rtrim(\ROOT_DIR, '/');
        }

        try {
            $public = $this->container->get('publicPath');
        } catch (\Throwable) {
            return null;
        }

        return is_string($public) ? rtrim(dirname($public), '/') : null;
    }

    private function startsNewBlock(string $line): bool
    {
        return (bool) preg_match('/^\s*```+/', $line)
            || (bool) preg_match('/^#{1,6}\s+/', $line)
            || (bool) preg_match('/^\s*>\s?/', $line)
            || (bool) preg_match('/^\s*([-*+]|\d+[.)])\s+/', $line)
            || (bool) preg_match('/^\s*([-*_])(\s*\1){2,}\s*$/', $line);
    }

    /**
     * @return array<int, string>
     */
    private function splitTableRow(string $line): array
    {
        $line = trim($line);
        $line = preg_replace('/^\||\|$/', '', $line) ?? $line;

        return array_map('trim', explode('|', $line));
    }

    private function slug(string $text): string
    {
        $text = preg_replace('/[*_`~]+/', '', $text) ?? $text; // marqueurs markdown
        $text = strip_tags($text);
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/', '-', trim($text)) ?? $text;

        return trim($text, '-');
    }
}