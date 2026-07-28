# Module View

Le module View intègre le moteur de template **Twig** dans NeoPHP. Il expose le `ViewManager` pour le rendu de templates, un système d'extensions permettant d'ajouter des fonctions et filtres Twig depuis n'importe quel module, et une extension de contrôleur qui injecte les méthodes `render()` et `template()` directement dans les contrôleurs.

---

## Sommaire

1. [Structure du module](#structure-du-module)
2. [Configuration Twig](#configuration-twig)
3. [ViewManager](#viewmanager)
4. [Extension de contrôleur : render() et template()](#extension-de-contrôleur--render-et-template)
5. [TwigExtensionInterface](#twigextensioninterface)
6. [Créer une extension Twig](#créer-une-extension-twig)
7. [Globals Twig](#globals-twig)
8. [Gestion des erreurs](#gestion-des-erreurs)
9. [ViewModule](#viewmodule)

---

## Structure du module

```
View/
├── ViewManager.php                     # Gestionnaire Twig principal
├── ViewModule.php                      # Enregistrement dans le conteneur DI
├── Interface/
│   └── TwigExtensionInterface.php      # Contrat pour les extensions Twig
├── Extension/
│   └── ViewControllerExtension.php     # Injecte render() et template() dans les contrôleurs
└── Exception/
    └── ViewException.php               # Exception spécifique aux erreurs de rendu
```

---

## Configuration Twig

La configuration est lue depuis deux fichiers : `twig.config.php` et `app.config.php`.

**`src/MonProjet/Config/twig.config.php` :**

```php
return [
    'cache'            => false,        // true en production
    'debug'            => true,         // Ajoute l'extension DebugExtension et {{ dump() }}
    'auto_reload'      => true,         // Recompile les templates modifiés
    'auto_escape'      => 'html',       // Échappement automatique HTML
    'charset'          => 'UTF-8',
    'strict_variables' => false,        // true = exception si variable inconnue
    'options'          => [],           // Options supplémentaires passées à Twig\Environment
];
```

**`src/MonProjet/Config/app.config.php` :**

```php
return [
    'date' => [
        'timezone' => 'Europe/Paris',   // Fuseau horaire Twig et PHP
    ],
    'general' => [
        'name'    => 'Mon Application',
        'version' => '1.0.0',
    ],
];
```

Le fuseau horaire est appliqué à la fois à PHP (`date_default_timezone_set`) et à Twig (`CoreExtension::setTimezone`).

Quand `cache` est `true`, les templates compilés sont stockés dans `Storage/var/cache/Twig/`.

---

## ViewManager

`Neo\Core\View\ViewManager` encapsule l'instance `Twig\Environment` et fournit les méthodes de rendu.

### Méthodes de rendu

```php
use Neo\Core\View\ViewManager;

$view = $container->get(ViewManager::class);

// Rendu avec exception si le template n'existe pas
$html = $view->render('articles/liste.twig', [
    'articles' => $articles,
    'titre'    => 'Tous les articles',
]);

// Rendu silencieux : retourne null si le template est introuvable
$html = $view->renderIfExists('partials/sidebar.twig', ['user' => $user]);
if ($html !== null) {
    // afficher la sidebar
}

// Accès direct à l'instance Twig\Environment
$twig = $view->getTwig();
```

### Ajout d'une extension Twig

```php
$view->addExtension(new MonExtension());
```

`addExtension()` parcourt les fonctions et filtres retournés par l'extension et les enregistre dans Twig via `TwigFunction` et `TwigFilter`. Chaque entrée peut être soit un callable direct, soit un tableau `['callable' => ..., 'options' => [...]]`.

---

## Extension de contrôleur : render() et template()

`Neo\Core\View\Extension\ViewControllerExtension` est une extension de contrôleur (annotée `#[Extension(type: ExtensionTypeEnum::CONTROLLER)]`) qui injecte automatiquement deux méthodes dans tous les contrôleurs.

### render()

Rend un template Twig et retourne directement un objet `Response` avec le header `Content-Type: text/html; charset=UTF-8`.

```php
use Neo\Core\Controller\AbstractController;

class ArticleController extends AbstractController
{
    public function liste(): Response
    {
        $articles = $this->get(ArticleRepository::class)->findAll();

        return $this->render('articles/liste.twig', [
            'articles' => $articles,
        ]);
    }

    public function detail(int $id): Response
    {
        $article = $this->get(ArticleRepository::class)->findById($id);

        return $this->render('articles/detail.twig', [
            'article' => $article,
            'titre'   => $article->titre,
        ]);
    }
}
```

### template()

Rend un template Twig et retourne le **contenu HTML sous forme de chaîne** (sans créer de `Response`). Utile pour inclure un fragment dans une réponse plus complexe ou pour le rendu de composants partiels.

```php
class EmailController extends AbstractController
{
    public function envoyer(): Response
    {
        $contenuEmail = $this->template('emails/bienvenue.twig', [
            'nom' => 'Alice',
        ]);

        $this->get(Mailer::class)->send(
            to: 'alice@example.com',
            subject: 'Bienvenue',
            body: $contenuEmail
        );

        return $this->json(['message' => 'E-mail envoyé.']);
    }
}
```

### Variable globale `app` dans les templates

`ViewControllerExtension` enrichit la variable globale `app` disponible dans tous les templates en y ajoutant automatiquement la `Session` et le `Cookie` courants :

```twig
{# Accès aux données de session dans Twig #}
{% if app.session.get('user_id') %}
    Connecté en tant que : {{ app.session.get('user_name') }}
{% endif %}

{# Accès au nom de l'application (depuis general config) #}
<title>{{ app.name }}</title>

{# Accès aux cookies #}
{% if app.cookie.has('lang') %}
    Langue : {{ app.cookie.get('lang') }}
{% endif %}
```

---

## TwigExtensionInterface

`Neo\Core\View\Interface\TwigExtensionInterface` est le contrat que toute extension Twig doit implémenter.

```php
interface TwigExtensionInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getFunctions(): array;

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array;
}
```

Chaque méthode retourne un tableau indexé par le **nom de la fonction/filtre** dans Twig. La valeur peut être :

**Format simple (callable direct) :**

```php
'maFonction' => fn(string $param): string => strtoupper($param),
```

**Format étendu (avec options Twig) :**

```php
'maFonction' => [
    'callable' => fn(string $param): string => strtoupper($param),
    'options'  => ['is_safe' => ['html']],  // options TwigFunction/TwigFilter
],
```

---

## Créer une extension Twig

Pour exposer des fonctions ou filtres personnalisés dans les templates, il suffit de créer une classe implémentant `TwigExtensionInterface` et de l'annoter avec `#[Extension(type: ExtensionTypeEnum::VIEW)]` pour qu'elle soit détectée et enregistrée automatiquement.

```php
<?php
declare(strict_types=1);

namespace Neo\Src\MonProjet\Extension;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
class MonExtensionTwig implements TwigExtensionInterface
{
    public function getFunctions(): array
    {
        return [
            // Fonction simple
            'prix_formate' => fn(float $montant, string $devise = 'EUR'): string
                => number_format($montant, 2, ',', ' ') . ' ' . $devise,

            // Fonction avec options Twig (is_safe: html pour ne pas ré-échapper)
            'badge' => [
                'callable' => fn(string $label, string $couleur = 'blue'): string
                    => "<span class=\"badge badge-{$couleur}\">{$label}</span>",
                'options' => ['is_safe' => ['html']],
            ],

            // Accès à un service injecté
            'nb_articles' => fn(): int => $this->articleRepository->count(),
        ];
    }

    public function getFilters(): array
    {
        return [
            'initiales' => fn(string $nom): string => implode('', array_map(
                fn(string $mot): string => strtoupper($mot[0]),
                explode(' ', $nom)
            )),

            'truncate' => [
                'callable' => fn(string $texte, int $longueur = 100): string
                    => mb_strlen($texte) > $longueur
                        ? mb_substr($texte, 0, $longueur) . '...'
                        : $texte,
                'options' => [],
            ],
        ];
    }
}
```

**Usage dans les templates :**

```twig
{# Fonctions #}
{{ prix_formate(article.prix) }}
{{ prix_formate(article.prix, 'USD') }}
{{ badge('Nouveau', 'green') }}
{{ nb_articles() }} articles disponibles

{# Filtres #}
{{ user.nom|initiales }}
{{ article.description|truncate(150) }}
```

---

## Globals Twig

La variable `app` est disponible globalement dans tous les templates. Elle est construite depuis la section `general` de `app.config.php`.

```php
// app.config.php
return [
    'general' => [
        'name'        => 'MonSite',
        'version'     => '2.1.0',
        'maintenance' => false,
        'support'     => 'support@monsite.fr',
    ],
];
```

```twig
{# Dans n'importe quel template #}
<title>{{ app.name }}</title>
<meta name="version" content="{{ app.version }}">

{% if app.maintenance %}
    <div class="alert">Site en maintenance.</div>
{% endif %}

{# Enrichi par ViewControllerExtension lors d'un render() de contrôleur #}
Bonjour {{ app.session.get('user_name') ?? 'visiteur' }}
```

---

## Gestion des erreurs

Le `ViewManager` convertit les exceptions Twig en `ViewException` avec des codes HTTP appropriés :

| Exception Twig | Code | Titre |
|---|---|---|
| `Twig\Error\LoaderError` | 404 | Template Not Found |
| `Twig\Error\SyntaxError` | 500 | Template Syntax Error |
| `Twig\Error\RuntimeError` | 500 | Template Runtime Error |

`renderIfExists()` intercepte silencieusement les `LoaderError` et retourne `null`, sans lever d'exception.

```php
// Dans un service, traiter les erreurs de template
try {
    $html = $view->render('mon-template.twig', $data);
} catch (ViewException $e) {
    // $e->getCode() retourne 404 si le template est introuvable
    // $e->getMessage() contient le détail de l'erreur Twig
    logger()->error($e->getMessage());
    $html = '<p>Erreur de rendu.</p>';
}
```

---

## ViewModule

`Neo\Core\View\ViewModule` enregistre le `ViewManager` dans le conteneur DI avec `ConfigModule` comme dépendance.

```php
// Enregistrement automatique par le framework
// Le module déclare sa dépendance sur ConfigModule
class ViewModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [ConfigModule::class];
    }

    public function register(Container $container): void
    {
        $container->set(ViewManager::class, fn(Container $c) => new ViewManager($c));
    }

    public function init(Container $container): object
    {
        return $container->get(ViewManager::class);
    }
}
```

Le `ViewManager` est instancié une seule fois (singleton dans le conteneur) et partagé entre tous les contrôleurs et services qui en ont besoin.
