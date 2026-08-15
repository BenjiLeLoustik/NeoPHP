# Asset

The `Asset` module manages the static file processing pipeline (CSS, JS, LESS) in NeoPHP. It automatically compiles, minifies and versions assets depending on the environment, exposes an `asset()` Twig function for views, and provides a CLI command to force recompilation.

---

## Summary

- [AssetManager](#assetmanager)
- [Compilers](#compilers)
  - [CssCompiler](#csscompiler)
  - [JsCompiler](#jscompiler)
  - [LessCompiler](#lesscompiler)
- [Twig Extension](#twig-extension--assetviewextension)
- [Commands](#commands)
  - [asset:reload](#assetreload)

---

## AssetManager

**File:** `AssetManager.php`

`AssetManager` is the central component of the module. It resolves the public path of an asset from its logical path relative to the project's `Assets/` folder.

### Initialization

On startup, it reads the application configuration to determine the environment (`prod` or any other value), loads the container's paths and attempts to read the current project's `manifest.json` file.

```php
// Automatically injected via the dependency container
$assetManager = $container->get(AssetManager::class);
```

### Resolving an asset: `getAssetPath(string $path)`

Behavior differs depending on the environment.

#### In `prod` mode

The manifest is the source of truth. If the path is present in the manifest, the versioned URL is returned directly with no filesystem access at all. If the path is absent from the manifest, a fallback path is built.

```
/builds/MyProject/assets/css/app.css
```

#### In `dev` mode (or any environment other than `prod`)

The source asset is inspected on every request:

1. The source file is checked in `src/MyProject/Assets/`.
2. An MD5 hash (8 characters) is computed from the file's content.
3. If the manifest already contains a path with this hash, it is returned directly (no recompilation).
4. Otherwise, the asset is compiled, the previously compiled version is deleted, and the manifest is updated.

```
/builds/MyProject/assets/css/app-a1b2c3d4.min.css
```

### Compiled file naming convention

| Type   | Source file         | Compiled file                            |
|--------|----------------------|--------------------------------------------|
| CSS    | `css/app.css`        | `css/app-{hash}.min.css`                    |
| JS     | `js/app.js`           | `js/app-{hash}.min.js`                      |
| LESS   | `css/theme.less`      | `css/theme-{hash}.css`                      |
| Other  | `fonts/icon.woff`      | `fonts/icon-{hash}.woff` (plain copy)       |

The `.min` suffix is automatically added for the `css` and `js` types.

### Manifest

The manifest is a JSON file located at `public/builds/{Project}/manifest.json`. It maps each asset's logical path to its versioned public path.

```json
{
    "css/app.css": "/builds/MyProject/assets/css/app-a1b2c3d4.min.css",
    "js/app.js": "/builds/MyProject/assets/js/app-f5e6d7c8.min.js"
}
```

---

## Compilers

All compilers implement `CompilerInterface`:

```php
interface CompilerInterface
{
    public function compile(string $source, string $target): void;
}
```

`AssetManager` automatically selects the right compiler based on the source file's extension via a `match`:

```php
$compiler = match($ext) {
    'css'  => new CssCompiler(),
    'js'   => new JsCompiler(),
    'less' => new LessCompiler(),
    default => null  // Plain copy for other types
};
```

### CssCompiler

**File:** `Compiler/CssCompiler.php`

Uses the `matthiasmullie/minify` library to minify CSS files.

```php
class CssCompiler implements CompilerInterface
{
    public function compile(string $source, string $target): void
    {
        $minifier = new CSS($source);
        $minifier->minify($target);
    }
}
```

**Composer dependency:** `matthiasmullie/minify`

### JsCompiler

**File:** `Compiler/JsCompiler.php`

Uses the same `matthiasmullie/minify` library to minify JavaScript files.

```php
class JsCompiler implements CompilerInterface
{
    public function compile(string $source, string $target): void
    {
        $minifier = new JS($source);
        $minifier->minify($target);
    }
}
```

**Composer dependency:** `matthiasmullie/minify`

### LessCompiler

**File:** `Compiler/LessCompiler.php`

Compiles LESS files into CSS via `less.php`. The resulting CSS is normalized (multiple spaces collapsed to one) before being written to disk.

```php
class LessCompiler implements CompilerInterface
{
    public function compile(string $source, string $target): void
    {
        $parser = new \Less_Parser();
        $parser->parseFile($source, dirname($source) . '/');
        $css = $parser->getCss();
        $css = preg_replace('/\s+/', ' ', $css);
        file_put_contents($target, $css);
    }
}
```

**Composer dependency:** `wikimedia/less.php`

The source file's parent folder is passed as the base URL to the parser, which enables relative imports (`@import 'variables.less'`).

---

## Twig Extension: `AssetViewExtension`

**File:** `Extension/AssetViewExtension.php`

This extension registers the `asset()` Twig function, which resolves the versioned public path of an asset from within templates. It is automatically detected and loaded via the `#[Extension(type: ExtensionTypeEnum::VIEW)]` attribute.

### Usage in Twig templates

```twig
{# Stylesheet #}
<link rel="stylesheet" href="{{ asset('css/app.css') }}">

{# JS script #}
<script src="{{ asset('js/app.js') }}"></script>

{# Other file (image, font...) #}
<img src="{{ asset('images/logo.png') }}" alt="Logo">
```

The path passed to `asset()` is relative to the current project's `Assets/` folder. In production, the versioned URL is read from the manifest. In development, compilation is triggered if the file has changed.

### Implementation

```php
#[Extension(type: ExtensionTypeEnum::VIEW)]
final readonly class AssetViewExtension implements TwigExtensionInterface
{
    public function __construct(private AssetManager $handler) {}

    public function getFunctions(): array
    {
        return [
            'asset' => [
                'callable' => fn(string $path) => $this->handler->getAssetPath($path),
                'options' => [],
            ],
        ];
    }

    public function getFilters(): array { return []; }
}
```

---

## Commands

### `asset:reload`

**File:** `Command/AssetReloadCommand.php`

Deletes a project's build folder, forcing recompilation of all assets on the next request.

#### Synopsis

```bash
php bin/neo asset:reload --project=<ProjectName>
```

#### Options

| Name        | Type   | Description                                    |
|--------------|--------|--------------------------------------------------|
| `--project` | Option | Name of the project whose builds are deleted     |

#### Behavior

1. Checks that the `public/builds/{Project}` folder exists.
2. Asks for interactive confirmation.
3. Recursively deletes the build folder.

```bash
php bin/neo asset:reload --project=MyProject
# → Delete build folder for 'MyProject' ? [y/N] : y
# → Build folder deleted for project 'MyProject'.
```

After this command, the first access to each page will automatically recompile and version all of the project's assets.

---

## Complete processing flow

```
Twig Template                    AssetManager               File System
     │                               │                            │
     │  {{ asset('css/app.css') }}   │                            │
     │──────────────────────────────►│                            │
     │                               │── Read manifest.json ─────►│
     │                               │◄── manifest loaded ────────│
     │                               │                            │
     │                    [ENV=dev]  │── Read source MD5 hash ───►│
     │                               │◄── hash computed ──────────│
     │                               │                            │
     │             [different hash]  │── CssCompiler.compile() ──►│
     │                               │◄── .min.css file created ──│
     │                               │── Save manifest ──────────►│
     │                               │                            │
     │◄──  /builds/MyProject/assets/css/app-a1b2c3d4.min.css  ───│
```

---

## File structure

```
neo/Core/Asset/
├── AssetManager.php                    # Main manager
├── Exception/
│   └── AssetException.php
├── Compiler/
│   ├── Interface/
│   │   └── CompilerInterface.php
│   ├── CssCompiler.php                 # CSS minification
│   ├── JsCompiler.php                  # JS minification
│   └── LessCompiler.php               # LESS → CSS compilation
├── Extension/
│   └── AssetViewExtension.php          # asset() Twig function
└── Commands/
    └── AssetReloadCommand.php          # asset:reload
```