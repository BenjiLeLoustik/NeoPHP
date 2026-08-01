# Markdown

Lightweight Markdown parser with no external dependencies. Converts Markdown text (or a `.md` file) into an array of `AbstractBlock` objects, usable directly in Twig templates via the `markdown_blocks()` function and the `md_inline` filter.

---

## Summary

1. [Module Structure](#module-structure)
2. [Block Hierarchy](#block-hierarchy)
   - [AbstractBlock](#abstractblock)
   - [HeadingBlock](#headingblock)
   - [ParagraphBlock](#paragraphblock)
   - [CodeBlock](#codeblock)
   - [ListBlock](#listblock)
   - [TableBlock](#tableblock)
   - [QuoteBlock](#quoteblock)
   - [HrBlock](#hrblock)
3. [MarkdownManager](#markdownmanager)
   - [`blocks()` Method](#blocks-method)
   - [`parse()` Method](#parse-method)
   - [`parseFile()` Method](#parsefile-method)
   - [`renderInline()` Method](#renderinline-method)
4. [MarkdownViewExtension](#markdownviewextension)
   - [Twig Function `markdown_blocks()`](#twig-function-markdown_blocks)
   - [Twig Filter `md_inline`](#twig-filter-md_inline)
5. [Rendering Template](#rendering-template)
6. [MarkdownModule](#markdownmodule)
7. [MarkdownException](#markdownexception)

---

## Module Structure

```
Tools/Markdown/
├── MarkdownManager.php                  # Main parser
├── MarkdownModule.php                   # Registration in the DI container
├── Block/
│   ├── AbstractBlock.php                # Common base class
│   ├── HeadingBlock.php
│   ├── ParagraphBlock.php
│   ├── CodeBlock.php
│   ├── ListBlock.php
│   ├── TableBlock.php
│   ├── QuoteBlock.php
│   └── HrBlock.php
├── Extension/
│   └── MarkdownViewExtension.php        # Twig function and filter
└── Exception/
    └── MarkdownException.php            # Specific exception
```

---

## Block Hierarchy

**Folder:** `Block/`

`MarkdownManager::parse()` (and therefore `blocks()` / `parseFile()`) no longer returns generic associative arrays, but a list of typed objects, each inheriting from `AbstractBlock`. Each block type has its own shape (`readonly` properties), which brings autocompletion and static checking on the PHP side — while remaining transparent on the Twig side, where `block.type`, `block.text`, etc. continue to work exactly as before (Twig reads an array key or a public property indifferently).

### AbstractBlock

Abstract class common to all blocks. Carries only the `type` property, set by each subclass.

```php
abstract class AbstractBlock
{
    public function __construct(
        public readonly string $type,
    ) {}
}
```

### HeadingBlock

Heading (`#` to `######`).

```php
public readonly int $level;    // 1 to 6
public readonly string $text;
public readonly string $slug;  // For HTML anchors
```

### ParagraphBlock

Text paragraph.

```php
public readonly string $text;
```

### CodeBlock

Code block delimited by triple backtick fences (```).

```php
public readonly string $language; // Empty if not specified
public readonly string $content;
```

### ListBlock

Bulleted or numbered list.

```php
public readonly bool $ordered;   // true for 1. 2. 3. ...
public readonly array $items;    // list<string>
```

### TableBlock

Markdown table.

```php
public readonly array $header;   // list<string>
public readonly array $rows;     // list<list<string>>
```

### QuoteBlock

Quote (`>`).

```php
public readonly string $content; // Lines joined by \n
```

### HrBlock

Horizontal separator (`---`, `***`, `___`). Carries no data beyond `type`.

---

## MarkdownManager

**File:** `MarkdownManager.php`

Entry point of the parser. Accepts both a raw Markdown string and a `.md` file path. Results are cached in memory (based on the resolved path and the file's `filemtime`).

### `blocks()` Method

```php
public function blocks(string $input): array // list<AbstractBlock>
```

Main method. Its behavior depends on `$input`:

- If `$input` ends with `.md` or `.markdown` (with no line break), it is treated as a file path.
   - The file is looked up as an absolute path, then relative to `ROOT_DIR` or `publicPath`.
   - If the file is not found, a `MarkdownException` (code 404) is thrown with a diagnostic indicating the missing segment in the tree.
- Otherwise, `$input` is parsed directly as Markdown content.

```php
$manager = $container->get(MarkdownManager::class);

// From a file
$blocks = $manager->blocks('neo/Core/Asset/README.md');

// From a string
$blocks = $manager->blocks("# Title\n\nA paragraph.");

foreach ($blocks as $block) {
    if ($block instanceof HeadingBlock) {
        echo $block->text;
    }
}
```

### `parse()` Method

```php
public function parse(string $markdown): array // list<AbstractBlock>
```

Parses a Markdown string and returns the list of `AbstractBlock` objects. Processes blocks in order: `CodeBlock`, `HeadingBlock`, `HrBlock`, `QuoteBlock`, `TableBlock`, `ListBlock`, `ParagraphBlock`.

```php
$blocks = $manager->parse("## Title\n\nContent.");
```

### `parseFile()` Method

```php
public function parseFile(string $path): array // list<AbstractBlock>
```

Resolves the file path (absolute or relative to the project root), reads its content, and calls `parse()`. The result is cached with the key `{resolved_path}:{filemtime}` to avoid unnecessary re-parsing.

Throws a `MarkdownException` (code 404) if the file cannot be found, or (code 500) if it cannot be read.

### `renderInline()` Method

```php
public function renderInline(string $text): string
```

Applies inline formatting to a text: first escapes HTML via `htmlspecialchars`, then converts inline Markdown syntax into HTML tags.

| Markdown Syntax          | HTML Result |
|--------------------------|---------------|
| `'''code'''`             | `<code>code</code>` |
| `![alt] (url)`           | `<img src="url" alt="alt">` |
| `[text] (url)`           | `<a href="url">text</a>` |
| `**bold**` or `__bold__` | `<strong>bold</strong>` |
| `*italic*` or `_italic_` | `<em>italic</em>` |

---

## MarkdownViewExtension

**File:** `Extension/MarkdownViewExtension.php`

Twig extension automatically registered via the `#[Extension(type: ExtensionTypeEnum::VIEW)]` attribute. It exposes a Twig function and filter. No change related to the block hierarchy: it simply delegates to `MarkdownManager`, which now returns objects instead of arrays.

### Twig Function `markdown_blocks()`

Calls `MarkdownManager::blocks()` from a Twig template.

```twig
{# From a .md file #}
{% set blocks = markdown_blocks('path/to/file.md') %}

{# From a variable containing raw Markdown #}
{% set blocks = markdown_blocks(article.content) %}
```

### Twig Filter `md_inline`

Applies `MarkdownManager::renderInline()` to a string. Marked `is_safe: html`, the result is not re-escaped by Twig.

```twig
{# In a template #}
<p>{{ block.text|md_inline|raw }}</p>
<li>{{ item|md_inline|raw }}</li>
```

> **Note:** The filter already produces escaped HTML (via `htmlspecialchars`). Using `|raw` is only necessary if the Twig config has `auto_escape` enabled — which is the default in NeoPHP.

---

## Rendering Template

NeoPHP includes a ready-to-use Twig template to display a full Markdown document.

**File:** `src/<Project>/Templates/markdown/document.html.twig`

```twig
{% include 'markdown/document.html.twig'
    with { blocks: markdown_blocks('neo/Core/Asset/README.md') } %}
```

This template iterates over the blocks and generates the corresponding HTML for each type (`heading`, `paragraph`, `code`, `list`, `table`, `quote`, `hr`). It wraps everything in `<div class="markdown-body">`.

Since Twig accesses array keys and an object's public properties indifferently, this template did not need to be modified after the switch to `AbstractBlock` objects — `block.type`, `block.text`, `block.level`, etc. continue to work identically.

**Custom display without the template:**

```twig
{% for block in markdown_blocks('docs/guide.md') %}
    {% if block.type == 'heading' %}
        <h{{ block.level }}>{{ block.text|md_inline|raw }}</h{{ block.level }}>
    {% elseif block.type == 'paragraph' %}
        <p>{{ block.text|md_inline|raw }}</p>
    {% elseif block.type == 'code' %}
        <pre><code class="language-{{ block.language }}">{{ block.content }}</code></pre>
    {% endif %}
{% endfor %}
```

---

## MarkdownModule

**File:** `MarkdownModule.php`

Registers `MarkdownManager` as a singleton service in the DI container. Declares `ViewModule` as a dependency so that `MarkdownViewExtension` can be injected into Twig.

```php
// Direct resolution from the container
$manager = $container->get(MarkdownManager::class);
```

---

## MarkdownException

**File:** `Exception/MarkdownException.php`

Extends `FrameworkException`. Thrown in two cases:

| Code | Cause |
|------|-------|
| `404` | Markdown file not found. The message includes a diagnostic of the missing segment in the tree. |
| `500` | File found but unreadable (`file_get_contents` failed). |

```php
try {
    $blocks = $manager->blocks('docs/missing.md');
} catch (\Neo\Core\Tools\Markdown\Exception\MarkdownException $e) {
    // $e->getCode() === 404
    // $e->getMessage() contains the resolved path and the first missing segment
}
```