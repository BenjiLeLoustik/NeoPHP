# Module Asset

Le module `Asset` gère le pipeline de traitement des fichiers statiques (CSS, JS, LESS) dans NeoPHP. Il compile, minifie et versionne automatiquement les assets selon l'environnement, expose une fonction Twig `asset()` pour les vues, et fournit une commande CLI pour forcer la recompilation.

---

## Sommaire

- [AssetManager](#assetmanager)
- [Compilateurs](#compilateurs)
  - [CssCompiler](#csscompiler)
  - [JsCompiler](#jscompiler)
  - [LessCompiler](#lesscompiler)
- [Extension Twig](#extension-twig--assetviewextension)
- [Commandes](#commandes)
  - [asset:reload](#assetreload)

---

## AssetManager

**Fichier :** `AssetManager.php`

`AssetManager` est le composant central du module. Il résout le chemin public d'un asset à partir de son chemin logique relatif au dossier `Assets/` du projet.

### Initialisation

Au démarrage, il lit la configuration de l'application pour déterminer l'environnement (`prod` ou tout autre valeur), charge les chemins du conteneur et tente de lire le fichier `manifest.json` du projet courant.

```php
// Injecté automatiquement via le conteneur de dépendances
$assetManager = $container->get(AssetManager::class);
```

### Résolution d'un asset : `getAssetPath(string $path)`

Le comportement diffère selon l'environnement.

#### En mode `prod`

Le manifest est la source de vérité. Si le chemin est présent dans le manifest, l'URL versionnée est retournée directement sans aucun accès au système de fichiers. Si le chemin est absent du manifest, un chemin de fallback est construit.

```
/builds/MyProject/assets/css/app.css
```

#### En mode `dev` (ou tout environnement autre que `prod`)

L'asset source est inspecté à chaque requête :

1. Le fichier source est vérifié dans `src/MyProject/Assets/`.
2. Un hash MD5 (8 caractères) est calculé sur le contenu du fichier.
3. Si le manifest contient déjà un chemin avec ce hash, il est retourné directement (pas de recompilation).
4. Sinon, l'asset est compilé, l'ancienne version compilée est supprimée, et le manifest est mis à jour.

```
/builds/MyProject/assets/css/app-a1b2c3d4.min.css
```

### Convention de nommage des fichiers compilés

| Type   | Fichier source     | Fichier compilé                          |
|--------|--------------------|------------------------------------------|
| CSS    | `css/app.css`      | `css/app-{hash}.min.css`                 |
| JS     | `js/app.js`        | `js/app-{hash}.min.js`                   |
| LESS   | `css/theme.less`   | `css/theme-{hash}.css`                   |
| Autre  | `fonts/icon.woff`  | `fonts/icon-{hash}.woff` (copie simple)  |

Le suffixe `.min` est ajouté automatiquement pour les types `css` et `js`.

### Manifest

Le manifest est un fichier JSON situé dans `public/builds/{Project}/manifest.json`. Il associe le chemin logique de chaque asset à son chemin public versionné.

```json
{
    "css/app.css": "/builds/MyProject/assets/css/app-a1b2c3d4.min.css",
    "js/app.js": "/builds/MyProject/assets/js/app-f5e6d7c8.min.js"
}
```

---

## Compilateurs

Tous les compilateurs implémentent `CompilerInterface` :

```php
interface CompilerInterface
{
    public function compile(string $source, string $target): void;
}
```

`AssetManager` sélectionne automatiquement le bon compilateur selon l'extension du fichier source via un `match` :

```php
$compiler = match($ext) {
    'css'  => new CssCompiler(),
    'js'   => new JsCompiler(),
    'less' => new LessCompiler(),
    default => null  // Copie simple pour les autres types
};
```

### CssCompiler

**Fichier :** `Compiler/CssCompiler.php`

Utilise la bibliothèque `matthiasmullie/minify` pour minifier les fichiers CSS.

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

**Dépendance Composer :** `matthiasmullie/minify`

### JsCompiler

**Fichier :** `Compiler/JsCompiler.php`

Utilise la même bibliothèque `matthiasmullie/minify` pour minifier les fichiers JavaScript.

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

**Dépendance Composer :** `matthiasmullie/minify`

### LessCompiler

**Fichier :** `Compiler/LessCompiler.php`

Compile les fichiers LESS en CSS via `less.php`. Le CSS produit est normalisé (espaces multiples réduits à un seul) avant d'être écrit sur le disque.

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

**Dépendance Composer :** `wikimedia/less.php`

Le dossier parent du fichier source est passé comme base URL au parser, ce qui permet les imports relatifs (`@import 'variables.less'`).

---

## Extension Twig : `AssetViewExtension`

**Fichier :** `Extension/AssetViewExtension.php`

Cette extension enregistre la fonction Twig `asset()` qui résout le chemin public versionné d'un asset depuis les templates. Elle est détectée et chargée automatiquement via l'attribut `#[Extension(type: ExtensionTypeEnum::VIEW)]`.

### Utilisation dans les templates Twig

```twig
{# Feuille de style #}
<link rel="stylesheet" href="{{ asset('css/app.css') }}">

{# Script JS #}
<script src="{{ asset('js/app.js') }}"></script>

{# Autre fichier (image, police...) #}
<img src="{{ asset('images/logo.png') }}" alt="Logo">
```

Le chemin passé à `asset()` est relatif au dossier `Assets/` du projet courant. En production, l'URL versionnée est lue depuis le manifest. En développement, la compilation est déclenchée si le fichier a changé.

### Implémentation

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

## Commandes

### `asset:reload`

**Fichier :** `Commands/AssetReloadCommand.php`

Supprime le dossier de builds d'un projet, forçant ainsi la recompilation de tous les assets à la prochaine requête.

#### Synopsis

```bash
php bin/neo asset:reload --project=<ProjectName>
```

#### Options

| Nom         | Type   | Description                              |
|-------------|--------|------------------------------------------|
| `--project` | Option | Nom du projet dont les builds sont supprimés |

#### Comportement

1. Vérifie l'existence du dossier `public/builds/{Project}`.
2. Demande une confirmation interactive.
3. Supprime récursivement le dossier de builds.

```bash
php bin/neo asset:reload --project=MyProject
# → Delete build folder for 'MyProject' ? [y/N] : y
# → Build folder deleted for project 'MyProject'.
```

Après cette commande, le premier accès à chaque page recompilera et versionnera automatiquement tous les assets du projet.

---

## Flux de traitement complet

```
Template Twig                    AssetManager               Système de fichiers
     │                               │                            │
     │  {{ asset('css/app.css') }}   │                            │
     │──────────────────────────────►│                            │
     │                               │── Lire manifest.json ─────►│
     │                               │◄── manifest chargé ────────│
     │                               │                            │
     │                    [ENV=dev]  │── Lire hash MD5 source ───►│
     │                               │◄── hash calculé ───────────│
     │                               │                            │
     │             [hash différent]  │── CssCompiler.compile() ──►│
     │                               │◄── fichier .min.css créé ──│
     │                               │── Sauvegarder manifest ───►│
     │                               │                            │
     │◄──  /builds/MyProject/assets/css/app-a1b2c3d4.min.css  ───│
```

---

## Structure des fichiers

```
neo/Core/Asset/
├── AssetManager.php                    # Gestionnaire principal
├── Exception/
│   └── AssetException.php
├── Compiler/
│   ├── Interface/
│   │   └── CompilerInterface.php
│   ├── CssCompiler.php                 # Minification CSS
│   ├── JsCompiler.php                  # Minification JS
│   └── LessCompiler.php               # Compilation LESS → CSS
├── Extension/
│   └── AssetViewExtension.php          # Fonction Twig asset()
└── Commands/
    └── AssetReloadCommand.php          # asset:reload
```
