<?php
declare(strict_types=1);

namespace Neo\Core\Tools\Markdown;

use Neo\Core\DI\Container;
use Neo\Core\Tools\Markdown\Block\AbstractBlock;
use Neo\Core\Tools\Markdown\Block\CodeBlock;
use Neo\Core\Tools\Markdown\Block\HeadingBlock;
use Neo\Core\Tools\Markdown\Block\HrBlock;
use Neo\Core\Tools\Markdown\Block\ListBlock;
use Neo\Core\Tools\Markdown\Block\ParagraphBlock;
use Neo\Core\Tools\Markdown\Block\QuoteBlock;
use Neo\Core\Tools\Markdown\Block\TableBlock;
use Neo\Core\Tools\Markdown\Exception\MarkdownException;

class MarkdownManager
{
    private Container $container;

    /**
     * @var array<string, list<AbstractBlock>>
     */
    private array $cache = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @return list<AbstractBlock>
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

    /**
     * @param list<string> $paths
     * @param list<string> $filenames
     * @return list<array{path: string, relative: string, title: string, blocks: list<AbstractBlock>}>
     * @throws MarkdownException
     */
    public function getAllMarkdown(array $paths, array $filesnames = ['README.md', 'readme.md']): array
    {
        $docs = [];
        $wanted = array_map('strtolower', $filesnames);

        foreach ($paths as $path) {
            $root = rtrim(str_replace('\\', '/', $path), '/');

            if (!is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            /** @var \SplFileInfo $item */
            foreach ($iterator as $item) {
                if (!$item->isFile() || !in_array(strtolower($item->getFilename()), $wanted, true)) {
                    continue;
                }

                $file = str_replace('\\', '/', $item->getPathname());
                $blocks = $this->blocks($file);

                $parentName = basename(dirname($file));
                $docs[] = [
                    'path' => $file,
                    'relative' => ltrim(substr($file, strlen($root)), '/'),
                    'slug' => $this->slug(
                        in_array($parentName, ['.', '..', ''], true)
                            ? basename($file, '.md')
                            : $parentName
                    ),
                    'title' => $this->extractTitle($blocks) ?? $parentName,
                    'description' => $this->extractDescription($blocks),
                ];
            }
        }

        usort($docs, static fn (array $a, array $b): int => strcmp($a['relative'], $b['relative']));

        return $docs;
    }

    /**
     * @param list<AbstractBlock> $blocks
     */
    private function extractDescription(array $blocks): ?string
    {
        foreach ($blocks as $block) {
            if ($block instanceof ParagraphBlock) {
                return $block->getText();
            }
        }

        return null;
    }

    /**
     * @param list<AbstractBlock> $blocks
     */
    private function extractTitle(array $blocks): ?string
    {
        foreach ($blocks as $block) {
            if ($block instanceof HeadingBlock && $block->level === 1) {
                return $block->getText();
            }
        }

        return null;
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
     * @return list<AbstractBlock>
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
     * @return list<AbstractBlock>
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
                $blocks[] = new CodeBlock($language, implode("\n", $code));
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.*?)\s*#*\s*$/', $line, $m)) {
                $blocks[] = new HeadingBlock(strlen($m[1]), $m[2], $this->slug($m[2]));
                $i++;
                continue;
            }

            if (preg_match('/^\s*([-*_])(\s*\1){2,}\s*$/', $line)) {
                $blocks[] = new HrBlock();
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
                $blocks[] = new QuoteBlock(implode("\n", $quote));
                continue;
            }

            if (
                str_contains($line, '|')
                && isset($lines[$i + 1])
                && preg_match('/^\s*\|?[\s:|-]*-[\s:|-]*\|?\s*$/', $lines[$i + 1])
            ) {
                $header = $this->splitTableRow($line);
                $i += 2;
                $rows = [];
                while (
                    $i < $count
                    && trim($lines[$i]) !== ''
                    && str_contains($lines[$i], '|')
                ) {
                    $rows[] = $this->splitTableRow($lines[$i]);
                    $i++;
                }
                $blocks[] = new TableBlock($header, $rows);
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
                $blocks[] = new ListBlock($ordered, $items);
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
            $blocks[] = new ParagraphBlock(implode(' ', $paragraph));
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
     * @return list<string>
     */
    private function splitTableRow(string $line): array
    {
        $line = trim($line);
        $line = preg_replace('/^\||\|$/', '', $line) ?? $line;

        return array_map('trim', explode('|', $line));
    }

    private function slug(string $text): string
    {
        $text = preg_replace('/[*_`~]+/', '', $text) ?? $text;
        $text = strip_tags($text);
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/', '-', trim($text)) ?? $text;

        return trim($text, '-');
    }
}