# Markdown

Parseur Markdown léger sans dépendance externe. Convertit du texte Markdown (ou un fichier `.md`) en tableau d'objets `AbstractBlock`, utilisables directement dans les templates Twig via la fonction `markdown_blocks()` et le filtre `md_inline`.

---

## Sommaire

1. [Structure du module](#structure-du-module)
2. [Hiérarchie des blocs](#hiérarchie-des-blocs)
    - [AbstractBlock](#abstractblock)
    - [HeadingBlock](#headingblock)
    - [ParagraphBlock](#paragraphblock)
    - [CodeBlock](#codeblock)
    - [ListBlock](#listblock)
    - [TableBlock](#tableblock)
    - [QuoteBlock](#quoteblock)
    - [HrBlock](#hrblock)
3. [MarkdownManager](#markdownmanager)
    - [Méthode `blocks()`](#méthode-blocks)
    - [Méthode `parse()`](#méthode-parse)
    - [Méthode `parseFile()`](#méthode-parsefile)
    - [Méthode `renderInline()`](#méthode-renderinline)
4. [MarkdownViewExtension](#markdownviewextension)
    - [Fonction Twig `markdown_blocks()`](#fonction-twig-markdown_blocks)
    - [Filtre Twig `md_inline`](#filtre-twig-md_inline)
5. [Template de rendu](#template-de-rendu)
6. [MarkdownModule](#markdownmodule)
7. [MarkdownException](#markdownexception)

---

## Structure du module

```
Tools/Markdown/
├── MarkdownManager.php                  # Parseur principal
├── MarkdownModule.php                   # Enregistrement dans le conteneur DI
├── Block/
│   ├── AbstractBlock.php                # Classe de base commune
│   ├── HeadingBlock.php
│   ├── ParagraphBlock.php
│   ├── CodeBlock.php
│   ├── ListBlock.php
│   ├── TableBlock.php
│   ├── QuoteBlock.php
│   └── HrBlock.php
├── Extension/
│   └── MarkdownViewExtension.php        # Fonction et filtre Twig
└── Exception/
    └── MarkdownException.php            # Exception spécifique
```

---

## Hiérarchie des blocs

**Dossier :** `Block/`

`MarkdownManager::parse()` (et donc `blocks()` / `parseFile()`) ne retourne plus des tableaux associatifs génériques, mais une liste d'objets typés, chacun héritant d'`AbstractBlock`. Chaque type de bloc a sa propre forme (propriétés `readonly`), ce qui apporte l'autocomplétion et la vérification statique côté PHP — tout en restant transparent côté Twig, où `block.type`, `block.text`, etc. continuent de fonctionner exactement comme avant (Twig lit indifféremment une clé de tableau ou une propriété publique).

### AbstractBlock

Classe abstraite commune à tous les blocs. Porte uniquement la propriété `type`, fixée par chaque sous-classe.

```php
abstract class AbstractBlock
{
    public function __construct(
        public readonly string $type,
    ) {}
}
```

### HeadingBlock

Titre (`#` à `######`).

```php
public readonly int $level;    // 1 à 6
public readonly string $text;
public readonly string $slug;  // Pour les ancres HTML
```

### ParagraphBlock

Paragraphe de texte.

```php
public readonly string $text;
```

### CodeBlock

Bloc de code délimité par ``` ``` ```.

```php
public readonly string $language; // Vide si non spécifié
public readonly string $content;
```

### ListBlock

Liste à puces ou numérotée.

```php
public readonly bool $ordered;   // true pour 1. 2. 3. ...
public readonly array $items;    // list<string>
```

### TableBlock

Tableau Markdown.

```php
public readonly array $header;   // list<string>
public readonly array $rows;     // list<list<string>>
```

### QuoteBlock

Citation (`>`).

```php
public readonly string $content; // Lignes jointes par \n
```

### HrBlock

Séparateur horizontal (`---`, `***`, `___`). Ne porte aucune donnée en dehors de `type`.

---

## MarkdownManager

**Fichier :** `MarkdownManager.php`

Point d'entrée du parseur. Accepte aussi bien une chaîne Markdown brute qu'un chemin de fichier `.md`. Les résultats sont mis en cache en mémoire (basé sur le chemin résolu et le `filemtime` du fichier).

### Méthode `blocks()`

```php
public function blocks(string $input): array // list<AbstractBlock>
```

Méthode principale. Son comportement dépend de `$input` :

- Si `$input` termine par `.md` ou `.markdown` (sans saut de ligne), il est traité comme un chemin de fichier.
    - Le fichier est cherché en absolu, puis relatif à `ROOT_DIR` ou `publicPath`.
    - Si le fichier n'est pas trouvé, une `MarkdownException` (code 404) est levée avec un diagnostic indiquant le segment manquant dans l'arborescence.
- Sinon, `$input` est parsé directement comme contenu Markdown.

```php
$manager = $container->get(MarkdownManager::class);

// Depuis un fichier
$blocks = $manager->blocks('neo/Core/Asset/README.md');

// Depuis une chaîne
$blocks = $manager->blocks("# Titre\n\nUn paragraphe.");

foreach ($blocks as $block) {
    if ($block instanceof HeadingBlock) {
        echo $block->text;
    }
}
```

### Méthode `parse()`

```php
public function parse(string $markdown): array // list<AbstractBlock>
```

Parse une chaîne Markdown et retourne la liste d'objets `AbstractBlock`. Traite les blocs dans l'ordre : `CodeBlock`, `HeadingBlock`, `HrBlock`, `QuoteBlock`, `TableBlock`, `ListBlock`, `ParagraphBlock`.

```php
$blocks = $manager->parse("## Titre\n\nContenu.");
```

### Méthode `parseFile()`

```php
public function parseFile(string $path): array // list<AbstractBlock>
```

Résout le chemin du fichier (absolu ou relatif à la racine du projet), lit son contenu et appelle `parse()`. Le résultat est mis en cache avec la clé `{chemin_résolu}:{filemtime}` pour éviter les re-parsings inutiles.

Lève une `MarkdownException` (code 404) si le fichier est introuvable, ou (code 500) s'il ne peut pas être lu.

### Méthode `renderInline()`

```php
public function renderInline(string $text): string
```

Applique la mise en forme inline sur un texte : échappe d'abord le HTML via `htmlspecialchars`, puis transforme la syntaxe Markdown inline en balises HTML.

| Syntaxe Markdown | Résultat HTML |
|------------------|---------------|
| `` `code` `` | `<code>code</code>` |
| `![alt](url)` | `<img src="url" alt="alt">` |
| `[texte](url)` | `<a href="url">texte</a>` |
| `**gras**` ou `__gras__` | `<strong>gras</strong>` |
| `*italique*` ou `_italique_` | `<em>italique</em>` |

---

## MarkdownViewExtension

**Fichier :** `Extension/MarkdownViewExtension.php`

Extension Twig enregistrée automatiquement via l'attribut `#[Extension(type: ExtensionTypeEnum::VIEW)]`. Elle expose une fonction et un filtre Twig. Aucun changement lié à la hiérarchie de blocs : elle délègue simplement à `MarkdownManager`, qui renvoie désormais des objets au lieu de tableaux.

### Fonction Twig `markdown_blocks()`

Appelle `MarkdownManager::blocks()` depuis un template Twig.

```twig
{# Depuis un fichier .md #}
{% set blocks = markdown_blocks('chemin/vers/fichier.md') %}

{# Depuis une variable contenant du Markdown brut #}
{% set blocks = markdown_blocks(article.content) %}
```

### Filtre Twig `md_inline`

Applique `MarkdownManager::renderInline()` sur une chaîne. Marqué `is_safe: html`, le résultat n'est pas ré-échappé par Twig.

```twig
{# Dans un template #}
<p>{{ block.text|md_inline|raw }}</p>
<li>{{ item|md_inline|raw }}</li>
```

> **Note :** Le filtre produit déjà du HTML échappé (via `htmlspecialchars`). L'usage de `|raw` est nécessaire uniquement si la config Twig a `auto_escape` activé — ce qui est le cas par défaut dans NeoPHP.

---

## Template de rendu

NeoPHP inclut un template Twig prêt à l'emploi pour afficher un document Markdown complet.

**Fichier :** `src/<Projet>/Templates/markdown/document.html.twig`

```twig
{% include 'markdown/document.html.twig'
    with { blocks: markdown_blocks('neo/Core/Asset/README.md') } %}
```

Ce template itère sur les blocs et génère le HTML correspondant pour chaque type (`heading`, `paragraph`, `code`, `list`, `table`, `quote`, `hr`). Il encapsule le tout dans `<div class="markdown-body">`.

Comme Twig accède indifféremment aux clés de tableau et aux propriétés publiques d'un objet, ce template n'a pas eu besoin d'être modifié suite au passage aux objets `AbstractBlock` — `block.type`, `block.text`, `block.level`, etc. continuent de fonctionner à l'identique.

**Affichage personnalisé sans le template :**

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

**Fichier :** `MarkdownModule.php`

Enregistre le `MarkdownManager` comme service singleton dans le conteneur DI. Déclare `ViewModule` comme dépendance afin que la `MarkdownViewExtension` puisse être injectée dans Twig.

```php
// Résolution directe depuis le conteneur
$manager = $container->get(MarkdownManager::class);
```

---

## MarkdownException

**Fichier :** `Exception/MarkdownException.php`

Étend `FrameworkException`. Levée dans deux cas :

| Code | Cause |
|------|-------|
| `404` | Fichier Markdown introuvable. Le message inclut un diagnostic du segment manquant dans l'arborescence. |
| `500` | Fichier trouvé mais illisible (`file_get_contents` a échoué). |

```php
try {
    $blocks = $manager->blocks('docs/missing.md');
} catch (\Neo\Core\Tools\Markdown\Exception\MarkdownException $e) {
    // $e->getCode() === 404
    // $e->getMessage() contient le chemin résolu et le premier segment manquant
}
```