# Translation

Le module Translation offre un système d'internationalisation (i18n) complet pour les applications NeoPHP. Il gère la résolution automatique de la locale, le chargement de fichiers de traduction PHP par domaine, l'écriture automatique des clés manquantes en mode développement, ainsi qu'une intégration native avec Twig et le Profiler.

---

## Sommaire

1. [Structure du module](#structure-du-module)
2. [Configuration](#configuration)
3. [Fichiers de traduction](#fichiers-de-traduction)
4. [TranslationManager](#translationmanager)
5. [LocaleManager](#localemanager)
6. [TranslationDomain](#translationdomain)
7. [TranslationRegistry](#translationregistry)
8. [TranslationLoader et TranslationWriter](#translationloader-et-translationwriter)
9. [Intégration Twig](#intégration-twig)
10. [Helper global translate()](#helper-global-translate)
11. [TranslationCollector (Profiler)](#translationcollector-profiler)
12. [Commande translation:sync](#commande-translationsync)

---

## Structure du module

```
Translation/
├── TranslationManager.php              # Gestionnaire principal (implements TranslatorInterface)
├── TranslationModule.php               # Enregistrement dans le conteneur DI
├── Domain/
│   └── TranslationDomain.php           # Normalisation et résolution des domaines
├── Loader/
│   └── TranslationLoader.php           # Chargement des fichiers de traduction avec cache
├── Registry/
│   └── TranslationRegistry.php         # Registre statique des chemins de traduction
├── Locale/
│   └── LocaleManager.php               # Résolution de la locale active
├── Writer/
│   └── TranslationWriter.php           # Écriture automatique des clés manquantes
├── Collector/
│   └── TranslationCollector.php        # Collecteur pour le Profiler
├── Extension/
│   └── TranslationViewExtension.php    # Fonctions et filtres Twig
├── Helper/
│   └── Translate.php                   # Fonction globale translate()
├── Interface/
│   ├── TranslatorInterface.php
│   └── TranslationCollectorInterface.php
└── Commands/
    └── TranslationSyncCommand.php      # translation:sync
```

---

## Configuration

La configuration se trouve dans `src/MonProjet/Config/app.config.php` :

```php
return [
    'translation' => [
        'enabled'          => true,
        'default_locale'   => 'fr',
        'available_locales' => [
            'fr' => 'Français',
            'en' => 'English',
            'es' => 'Español',
        ],
    ],
];
```

| Clé | Type | Description |
|---|---|---|
| `enabled` | `bool` | Active ou désactive le système de traduction |
| `default_locale` | `string` | Locale utilisée par défaut |
| `available_locales` | `array` | Map `code => libellé` des locales disponibles |

Lorsque `enabled` vaut `false`, la méthode `translate()` retourne directement le texte d'origine avec les remplacements appliqués.

---

## Fichiers de traduction

Les traductions sont des fichiers PHP qui retournent un tableau associatif `clé => traduction`.

**Structure de dossiers :**

```
src/MonProjet/Translations/
├── fr/
│   ├── common.php      # Domaine par défaut
│   ├── auth.php
│   └── emails.php
└── en/
    ├── common.php
    ├── auth.php
    └── emails.php
```

**Exemple de fichier `fr/common.php` :**

```php
<?php

declare(strict_types=1);

return [
    'Bienvenue'                 => 'Bienvenue',
    'Bonjour :name'             => 'Bonjour :name',
    'Page non trouvée'          => 'Page non trouvée',
    'Une erreur est survenue'   => 'Une erreur est survenue',
];
```

**Exemple de fichier `en/common.php` :**

```php
<?php

declare(strict_types=1);

return [
    'Bienvenue'                 => 'Welcome',
    'Bonjour :name'             => 'Hello :name',
    'Page non trouvée'          => 'Page not found',
    'Une erreur est survenue'   => 'An error occurred',
];
```

Le chemin est résolu selon la formule : `{basePath}/{locale}/{domain}.php`

---

## TranslationManager

`Neo\Core\Translation\TranslationManager` est le point d'entrée principal. Il implémente `TranslatorInterface`.

### Traduire un texte

```php
use Neo\Core\Translation\TranslationManager;

$translator = $container->get(TranslationManager::class);

// Traduction simple
echo $translator->translate('Bienvenue');

// Traduction avec remplacements (:param)
echo $translator->translate('Bonjour :name', ['name' => 'Alice']);

// Traduction dans un domaine spécifique
echo $translator->translate('Connexion', [], 'auth');
```

Les remplacements fonctionnent avec la syntaxe `:nomDuParametre` dans la valeur traduite.

### Changer la locale

```php
// Change la locale active et enregistre un cookie (durée par défaut : 1 an)
$translator->setLocale('en');

// Durée personnalisée en secondes
$translator->setLocale('es', lifetime: 7200);
```

Si `available_locales` est défini dans la configuration et que la locale demandée n'en fait pas partie, une `TranslationException` (code 400) est levée.

### Autres méthodes

```php
$translator->getLocale();              // Retourne la locale active, ex: 'fr'
$translator->getLocales();             // Retourne le tableau available_locales
$translator->isEnabledTranslation();   // Retourne true si le système est actif
```

---

## LocaleManager

`Neo\Core\Translation\Locale\LocaleManager` est responsable de la **résolution automatique** de la locale au démarrage. Il suit cette priorité :

1. Cookie `lang` présent et valide → locale du cookie
2. Locale par défaut configurée (`default_locale`) si elle est dans `available_locales`
3. Fallback sur `'fr'`

```php
$locale = LocaleManager::resolve($container);
// Retourne ex: 'en' si le cookie lang=en est présent
```

---

## TranslationDomain

`Neo\Core\Translation\Domain\TranslationDomain` gère la normalisation des noms de domaine.

```php
use Neo\Core\Translation\Domain\TranslationDomain;

// Normalise null ou '' vers 'common'
TranslationDomain::normalize(null);   // 'common'
TranslationDomain::normalize('');     // 'common'
TranslationDomain::normalize('auth'); // 'auth'

// Résout le chemin complet vers un fichier de traduction
TranslationDomain::resolveFilePath('/app/Translations', 'fr', 'auth');
// Résultat : '/app/Translations/fr/auth.php'
```

La constante `TranslationDomain::DEFAULT` vaut `'common'`.

---

## TranslationRegistry

`Neo\Core\Translation\Registry\TranslationRegistry` maintient la liste statique des dossiers de traduction à consulter. Plusieurs chemins peuvent être enregistrés ; le `TranslationLoader` les parcourt tous et fusionne les résultats (`array_replace`).

```php
use Neo\Core\Translation\Registry\TranslationRegistry;

// Enregistrer un dossier de traductions
TranslationRegistry::registerPath('/app/src/Blog/Translations');
TranslationRegistry::registerPath('/app/src/Shared/Translations');

// Récupérer tous les chemins enregistrés
$paths = TranslationRegistry::getPaths();
```

---

## TranslationLoader et TranslationWriter

### TranslationLoader

`Neo\Core\Translation\Loader\TranslationLoader` charge les fichiers de traduction avec un **cache en mémoire** (par clé `domain:locale`). En mode développement, il invalide l'OPcache et le cache de stat PHP à chaque chargement pour garantir la fraîcheur des fichiers.

```php
$loader = new TranslationLoader();

// Charge fr/common.php depuis tous les chemins enregistrés
$translations = $loader->load('fr');

// Charge fr/auth.php
$translations = $loader->load('fr', 'auth');

// Invalide le cache pour forcer un rechargement
$loader->invalidate('fr', 'auth');
```

### TranslationWriter

`Neo\Core\Translation\Writer\TranslationWriter` est utilisé en mode développement (`environment === 'dev'`) pour **ajouter automatiquement les clés manquantes** dans le fichier de traduction correspondant. Cela évite d'avoir à créer manuellement chaque clé.

Lorsqu'une clé n'est pas trouvée, elle est ajoutée avec sa propre valeur comme traduction par défaut :

```php
// Dans fr/common.php, si 'Nouveau message' est absent, il devient :
// 'Nouveau message' => 'Nouveau message'
```

---

## Intégration Twig

`Neo\Core\Translation\Extension\TranslationViewExtension` expose automatiquement des fonctions et filtres Twig dès que le module Translation est actif.

### Fonctions disponibles dans les templates

```twig
{# Traduction simple #}
{{ translate('Bienvenue') }}

{# Alias court #}
{{ trans('Bienvenue') }}

{# Avec remplacements #}
{{ translate('Bonjour :name', {name: user.name}) }}

{# Dans un domaine spécifique #}
{{ translate('Connexion', {}, 'auth') }}

{# Locale active #}
{{ getLocale() }}

{# Toutes les locales disponibles #}
{% for code, label in getLocales() %}
    <a href="/lang/{{ code }}">{{ label }}</a>
{% endfor %}

{# Vérifier si la traduction est activée #}
{% if isEnabledTranslation() %}
    {# ... #}
{% endif %}
```

### Filtre disponible

```twig
{# Filtre Twig équivalent à trans() #}
{{ 'Bienvenue'|trans }}
{{ 'Bonjour :name'|trans({name: user.name}) }}
{{ 'Connexion'|trans({}, 'auth') }}
```

---

## Helper global translate()

Le fichier `Helper/Translate.php` définit la fonction globale `translate()`, disponible partout dans le code PHP sans injection de dépendances.

```php
// Dans un contrôleur, un service, ou n'importe quelle classe
$message = translate('Bienvenue');
$message = translate('Bonjour :name', ['name' => 'Alice']);
$message = translate('Connexion', [], 'auth');
```

La fonction résout automatiquement le `TranslationManager` depuis le `ContainerRegistry`.

---

## TranslationCollector (Profiler)

`Neo\Core\Translation\Collector\TranslationCollector` s'intègre au Profiler NeoPHP pour afficher en temps réel les informations de traduction durant la requête :

- Locale active
- Nombre de clés résolues (hits)
- Nombre de clés manquantes (misses)
- Tableau détaillé des hits et misses par domaine

Le tab du Profiler s'affiche en vert si toutes les clés sont résolues, en jaune si des clés manquent, en gris si la traduction est désactivée.

---

## Commande translation:sync

`translation:sync` scanne les fichiers source du projet (`src/MonProjet/`) à la recherche de tous les appels à `translate()`, `trans()`, les filtres Twig `|trans`, et les commentaires `// @translatable`, puis synchronise les fichiers de traduction.

```bash
# Voir ce qui serait ajouté/supprimé sans écrire
php neo translation:sync --project=Blog --dry-run

# Synchroniser (ajoute les clés manquantes)
php neo translation:sync --project=Blog

# Synchroniser et supprimer les clés obsolètes
php neo translation:sync --project=Blog --prune
```

**Options :**

| Option | Description |
|---|---|
| `--project` | Projet cible (dossier dans `src/`) |
| `--dry-run` | Affiche les différences sans écrire |
| `--prune` | Supprime les clés qui ne sont plus utilisées dans le code (destructif) |

**Patterns reconnus automatiquement :**

```php
// Appels PHP directs
translate('Ma clé');
translate('Ma clé', ['param' => 'valeur'], 'domain');
trans('Ma clé');

// Dans les templates Twig
{{ translate('Ma clé') }}
{{ 'Ma clé'|trans }}
{{ 'Ma clé'|trans({}, 'auth') }}

// Annotation dans les fichiers de config
'default_role' => 'admin', // @translatable
'default_role' => 'admin', // @translatable:auth
```

Les locales sont découvertes automatiquement depuis `app.config.php` (`available_locales`) ou depuis les dossiers existants dans le répertoire `Translations/`.

Chaque clé nouvelle est ajoutée avec elle-même comme valeur par défaut (`'Ma clé' => 'Ma clé'`), facilitant la traduction ultérieure.
