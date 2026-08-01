# ROADMAP — Suivi du framework NeoPHP

> Analyse complète du codebase
> Dernière mise à jour : 2026-08-01

---

## Légende des difficultés

| Icône | Niveau | Description |
|-------|--------|-------------|
| 🟢 | Facile | < 1h, modification localisée, pas de refacto |
| 🟡 | Moyen | 1h–4h, touche plusieurs fichiers ou nécessite tests |
| 🟠 | Difficile | 4h–1j, refacto architectural ou nouvelle couche |
| 🔴 | Très difficile | > 1j, nouveau sous-système complet |

---

## Structure générale

```
neo/Core/
├── Application/     → Détection du projet & environnement
├── Asset/           → Compilation CSS/JS/Less
├── Console/         → Framework CLI & générateurs
├── Controller/      → Contrôleur de base & extensions
├── Cron/            → Tâches planifiées
├── Database/        → Data Mapper ORM (EntityManager, EntityRepository, Mapping, Migrations, Formulaires)
├── DI/              → Conteneur d'injection de dépendances
├── Error/           → Gestion des erreurs & exceptions
├── Event/           → Système d'événements & dispatcher
├── Extension/       → Extensions utilitaires (String, Date, File, Html, Json, Number, Path, Url, Array)
├── Http/            → Request / Response / Session / Cookie / Flash
├── Module/          → Système de modules avec boot ordonné
├── Profiler/        → Barre de debug (env dev)
├── Routing/         → Routeur par attributs
├── Security/        → Auth, CSRF, Middleware
├── Testing/         → Scaffold PHPUnit, génération auto
├── Translation/     → Support i18n
├── Utils/           → Config, Cache, Logger, Mailer, Notification
├── Validator/       → Validation par contraintes attributs
└── View/            → Intégration Twig
```

---

## ✅ Ce qui est bon

### Architecture & Design
- **Système de modules** propre avec résolution des dépendances et auto-découverte
- **Routing par attributs PHP 8** (`#[Route]`, `#[MainRoute]`) — syntaxe propre et moderne
- **ORM Data Mapper** complet : `EntityManager`, `UnitOfWork`, `ProxyFactory`, `EntityRepository` — entités POPO pures sans classe mère
- **Relations ORM** : OneToOne, ManyToOne, OneToMany, ManyToMany — avec `Collection` et chargement lazy via proxies transparents
- **`make:entity` + `database:orm:diff`** — workflow interactif de création d'entités et de génération de migrations depuis le diff de schéma
- **Multi-base de données** — connexions PDO multiples avec tracking des migrations par connexion
- **Système d'événements** avec priorités sur les listeners, subscribers, cache en prod
- **Conteneur DI** conforme PSR-11 avec résolution par réflexion, détection des dépendances circulaires, codes d'erreur distincts (404/422/500)
- **Gestion des erreurs** différenciée : stack trace en dev, messages sûrs en prod
- **Système de formulaires** refactoré : `FormFactory`, `FormBuilder`, `FieldType` enum (10 types), CSRF, rendu Twig
- **Cron intégré** avec expressions cron standard, timezone et lock optionnel
- **CLI framework** intégré avec générateurs couvrant tout le workflow
- **Support Twig 3.x** avec extensions personnalisées, `twig/intl-extra`, cache de templates
- **Configuration** par fichiers PHP avec notation dot-notation
- **Migrations** avec pattern up/down, snapshots de schéma, détection de drift, support multi-connexion
- **Cache des routes** en production (JSON, plus de `unserialize()`)
- **Notifications** : Email, Slack, SMS (drivers Twilio / Vonage / Log) via `NotificationManager`
- **`#[IsGranted]`** — attribut de contrôle d'accès par rôle(s) sur classe ou méthode
- **Middlewares étendus** : `IsGrantedMiddleware`, `CsrfMiddleware`, `AuthRateLimitMiddleware`
- **Helpers contrôleur** : `getSession()`, `getFlash()`, `getCookie()` enregistrés via extensions

### Qualité du code
- Usage cohérent des **fonctionnalités PHP 8.5+** : attributs, propriétés typées, union types, named arguments
- **Typage fort** : retours et paramètres typés sur la grande majorité des méthodes
- **Respect PSR-4** pour l'autoloading
- **Docblocks** présents sur la majorité des méthodes
- **Nommage clair** et méthodes descriptives
- **QueryBuilder** avec binding de paramètres et identifiants quotés en backticks
- Hachage des mots de passe en **Argon2/bcrypt** (cost=12)
- **Régénération de session** à la connexion
- `hash_equals()` utilisé correctement pour la comparaison CSRF
- **Middleware sans `exit()`** — retour d'une Response propre
- **Gestion d'erreurs unifiée** — exceptions partout, plus de `false`/`null` implicites

### Sécurité (fixes appliqués)
- Messages d'exception échappés avec `htmlspecialchars()` dans `ErrorHandler` ✅
- Limite de taille `php://input` à 8 Mo dans `Request` ✅
- Validation `X-Forwarded-For` via `filter_var()` ✅
- Protection path traversal via `realpath()` + vérification de préfixe ✅
- Identifiants SQL quotés avec backticks dans `QueryBuilder` ✅
- Interpolation SQL directe supprimée dans l'ORM ✅
- `unserialize()` remplacé par `json_decode()` pour le cache de routes dans `Router` ✅
- `require_once` dynamique sécurisé avec whitelist dans `Router` ✅
- CSRF sur les formulaires d'authentification ✅
- Timeout de session dans `SessionGuard` ✅
- Upload sécurisé : validation MIME, limite de taille, assainissement du nom ✅
- Rate limiting sur les tentatives de connexion ✅
- `unserialize()` remplacé par `json_decode()` pour le cache des listeners dans `EventManager` ✅
- Parties statiques des routes passées dans `preg_quote()` dans `RouterManager::compilePattern()` ✅

---

## ❌ Ce qui reste à adresser

### Qualité du code résiduelle

| Problème | Localisation | Impact |
|----------|-------------|--------|
| **`@` operator** utilisé pour supprimer des erreurs à certains endroits | Divers | Faible |
| **Singleton statique du Container** (`getInstance()`) — état global, problématique pour les tests | `Container.php` | Moyen |
| **Tableaux de forme fixe** passés entre composants sans DTO — blocks Markdown, résultats scanner, metadata middleware, introspection DB, listeners, config rôle | Divers | Moyen |

---

## 🗂 Checklist de suivi

> Statut : `[ ]` à faire — `[~]` en cours

---

### 🔧 Améliorations — Architecture & Qualité

- [x] 🟡 **`AttributeScanResult` DTO** — remplace `array{target, attribute, arguments, type, reflection}` retourné par `ScannerAttributeManager`
  - Consommé par 4 composants : `RouterManager`, `MiddlewareManager`, `CronScanner`, `TestScanner`
  - Fichiers concernés : `neo/Core/Utils/Scanner/ScannerAttributeManager.php` et tous ses consommateurs

- [x] 🟡 **`MiddlewareMeta` DTO readonly** — remplace `array{class, message, onError, redirect, isClass, params, priority}` dans `MiddlewareManager`
  - Construit et consommé dans le même fichier, amélioration de lisibilité et de type safety
  - Fichier concerné : `neo/Core/Security/Middleware/MiddlewareManager.php`

- [x] 🟡 **Hiérarchie `AbstractBlock`** — remplace `array<string, mixed>` retourné par `MarkdownManager::parse()`
  - 7 types distincts avec des formes différentes : `HeadingBlock`, `CodeBlock`, `ParagraphBlock`, `ListBlock`, `TableBlock`, `QuoteBlock`, `HrBlock`
  - Fichiers concernés : `neo/Core/Tools/Markdown/MarkdownManager.php`, `neo/Core/Tools/Markdown/Extension/MarkdownViewExtension.php`

- [x] 🟠 **`ListenerRegistration` DTO** — remplace `array{class, priority, method, instance}` dans `EventManager`
  - Sérialisé/désérialisé en JSON, source d'erreurs silencieuses lors de la désérialisation
  - Fichier concerné : `neo/Core/Event/EventManager.php`

- [x] 🟠 **`ColumnMetadata`, `ForeignKeyMetadata`, `IndexMetadata` DTOs** — remplace les tableaux retournés par `DatabaseIntrospector`
  - Utilisés dans le pipeline de génération de migrations (`SchemaDiffer`, `MigrationGenerator`)
  - Fichier concerné : `neo/Core/Database/Access/Introspector/DatabaseIntrospector.php`

- [x] 🟠 **`RoleConfig` DTO** — partagé entre `SessionGuard` et `TokenGuard` pour la configuration des rôles
  - Les deux guards accèdent au même tableau `['relation', 'field', 'model']` sans contrat partagé
  - Fichiers concernés : `neo/Core/Security/Auth/Guard/SessionGuard.php`, `neo/Core/Security/Auth/Guard/TokenGuard.php`

- [ ] 🟠 **Remplacer le singleton statique du Container** par une injection via le kernel
  - `Container::getInstance()` crée un état global partagé entre tous les tests, rendant l'isolation impossible
  - Piste : passer l'instance de `Container` via le constructeur de `Neo\App`, puis la propager dans `ModuleManager` et les modules sans passer par le singleton
  - Fichiers concernés : `neo/Core/DI/Container.php`, `neo/App.php`, `neo/Core/Module/ModuleManager.php`
  - Bénéfice direct : les tests PHPUnit pourront créer des containers isolés sans état partagé

---

### ⚡ Améliorations — Performance

- [x] 🟢 **Connexions PDO persistentes** — option `persistent: true` dans `database.config.php`, propagée à `DatabaseConnection::connect()` via `PDO::ATTR_PERSISTENT`
  - Bénéfice : réduit le coût de reconnexion sur les serveurs FPM avec beaucoup de requêtes courtes
  - Attention : les transactions non commitées peuvent fuiter entre requêtes avec certains drivers, documenter la mise en garde

- [ ] 🟡 **Mettre en cache les résultats de Reflection**
  - `MetadataFactory` relit les attributs via `ReflectionClass` à chaque requête en dev — ajouter un cache mémoire par classe (tableau statique ou APCu)
  - `RouterManager` scanne les contrôleurs via `ReflectionClass` à chaque requête dev — déjà mis en cache JSON en prod, mais le cache dev est rechargé à chaque fois
  - Fichiers prioritaires : `neo/Core/Database/ORM/Mapping/MetadataFactory.php`, `neo/Core/Routing/RouterManager.php`

- [x] 🟡 **Contrôler le buffering de sortie** — `ob_start()` / `ob_end_clean()` autour du dispatch dans `App::run()` pour éviter tout flush prématuré avant que les headers soient envoyés

- [ ] 🟠 **Détection automatique des requêtes N+1** en mode dev
  - Dans `DatabaseManager` (ou le `QueryCollector`), compter les requêtes identiques (même table, même structure, paramètres différents) dans une même requête HTTP
  - Logguer un warning si le même pattern de requête est exécuté plus de N fois (seuil configurable)
  - Afficher dans le Profiler avec la stack trace du premier et dernier appel

- [ ] 🟠 **Optimiser le scan de contrôleurs en dev**
  - Actuellement : scan complet du dossier `Controllers/` à chaque requête via `ScannerAttributeManager`
  - Piste : comparer le `filemtime()` des fichiers avec la date du cache JSON — ne rescanner que les fichiers modifiés
  - Alternative plus robuste : utiliser `inotifywait` (Linux) ou un hash du dossier pour invalider le cache

---

### 🧪 Module Testing

- [ ] 🟡 **`TestCase` de base avec container isolé**
  - Scaffold présent mais `Container` non isolé entre tests (singleton statique)
  - Débloqué une fois le singleton Container résolu (voir section Architecture)
  - Chaque test doit pouvoir bootstrapper un `Neo\App` avec une config de test sans affecter les autres

- [ ] 🟡 **`DatabaseTestCase` avec rollback automatique**
  - Scaffold présent ; il faut valider que `PDO::beginTransaction()` + `rollback()` fonctionne bien dans `DatabaseTestCase::tearDown()`
  - Vérifier le comportement avec les migrations : la base de test doit être synchronisée avant la première suite
  - Tester avec au moins un `EntityRepository` réel pour valider la chaîne ORM complète en test

- [ ] 🟡 **`FeatureTestCase` (HttpTestCase)** — simulation de requêtes HTTP sans serveur
  - Scaffold présent ; valider que `Request::create()` peut simuler des méthodes POST avec body, headers et cookies
  - La réponse doit pouvoir être inspectée : status code, headers, body JSON
  - Tester un contrôleur avec middleware pour valider la pile complète

- [ ] 🟠 **`EntityFactory`** — générateur de données de test pour l'ORM Data Mapper
  - Équivalent des model factories Laravel mais adapté aux entités POPO
  - API cible : `EntityFactory::for(Post::class)->create(['title' => 'Test'])` persisté via `EntityManager`
  - Optionnel : `make()` pour créer sans persister, `createMany(10)` pour un batch

- [ ] 🟡 **Tests internes du framework** — couverture minimale des composants critiques
  - Priorité 1 : `RouterManager` (match de patterns, paramètres optionnels, requirements, middlewares)
  - Priorité 2 : `EntityManager` + `UnitOfWork` (persist, flush, remove, find, relations)
  - Priorité 3 : `Container` (autowiring, bindings, détection de dépendances circulaires)

---

### 💡 Nouvelles fonctionnalités — Priorité haute

- [ ] 🟠 **Validation avancée**
  - Contrainte `Unique` actuelle est un placeholder — implémenter la vérification réelle en base via `EntityManager`
  - Ajouter `Exists` : vérifie qu'une valeur existe dans une table (utile pour les foreign keys)
  - Validation imbriquée : valider un tableau d'objets (ex. lignes d'une commande)
  - Règles personnalisées via interface `ConstraintInterface` avec message dynamique
  - Validation dans les formulaires : déclencher le validateur sur `$form->isValid()` plutôt que manuellement

- [x] 🟠 **Classe `Paginator` standalone**
  - Actuellement `QueryBuilder::paginate()` retourne un tableau brut sans métadonnées
  - Créer `Paginator` avec : `getItems()`, `getCurrentPage()`, `getTotalPages()`, `getTotalItems()`, `hasNextPage()`, `hasPreviousPage()`, `getLinks()`
  - Extension Twig `paginator_links(paginator)` pour rendre la navigation
  - Brancher sur `EntityRepository` : `$repo->paginate(page: 1, perPage: 20)`

- [ ] 🟠 **API Resources**
  - Classe `AbstractResource` avec méthode `toArray(object $entity): array` à implémenter
  - `ResourceCollection` pour transformer une liste
  - Helper contrôleur `$this->resource(Post::class, $post)` → JsonResponse
  - Permet de découpler la sérialisation JSON de l'entité (ex. cacher `password`, renommer des champs, inclure des relations conditionnelles)

- [ ] 🟠 **Versioning d'API**
  - Option `version: 'v1'` sur `#[MainRoute]` ou préfixe explicite `/api/v1`
  - Alternative plus simple : convention de namespace (`App/Controllers/Api/V1/PostController`)
  - Documenter la pratique recommandée dans le README

---

### 💡 Nouvelles fonctionnalités — Priorité moyenne

- [ ] 🟠 **Cache avancé**
  - Driver APCu (sans dépendance externe, idéal pour serveurs PHP-FPM)
  - Driver Redis : ajouter le support des tags (`remember('key', ttl, fn, ['tag1'])`) et `flushByTag('tag1')`
  - Driver Redis : `flush()` par préfixe pour isoler les caches par projet en multi-tenant
  - Interface `CacheDriverInterface` déjà présente — brancher les nouveaux drivers dans `cache.config.php`

- [ ] 🟠 **Logging avancé**
  - Actuellement : un handler par channel — permettre plusieurs handlers simultanés (ex. fichier + Slack + base de données)
  - Rotation configurable par channel (actuellement globale) : `'rotation' => 'daily'` ou `'weekly'`
  - Handler Slack : envoyer via webhook les niveaux `critical` / `emergency`
  - Handler DB : écrire dans une table `neo_logs` pour consultation via l'interface d'administration

- [ ] 🔴 **Stockage de fichiers**
  - Abstraction disque via `StorageManager` : `local`, `ftp`, `s3`
  - API cible : `Storage::disk('s3')->put('avatars/user.jpg', $stream)`, `Storage::url('avatars/user.jpg')`
  - Remplacer le helper `upload()` du contrôleur par `Storage::disk('local')->upload($request->file('avatar'))`
  - Intégrer avec `league/flysystem` ou implémenter une abstraction légère

---

### 💡 Nouvelles fonctionnalités — Priorité basse / Nice to have

- [ ] 🟠 **Health check endpoint** — route `/health` native pour les déploiements containerisés
- [ ] 🟠 **Multi-langue dans les routes** — `/fr/produits/{id}` et `/en/products/{id}`
- [ ] 🔴 **Queue / Jobs** — système de files de traitement asynchrone
- [ ] 🔴 **Debugbar enrichie** — toolbar plus riche (requêtes N+1 visualisées, timeline, etc.)
- [ ] 🔴 **Multi-tenant** — routing et DB par tenant
- [ ] 🔴 **GraphQL** — couche GraphQL en complément du REST
- [ ] 🔴 **WebSockets** — support Swoole/Ratchet pour le temps réel

---

## Scores globaux

> Évaluation à la date du 2026-07-28, après réécriture complète de l'ORM et ajout des nouvelles fonctionnalités.

| Critère | Note | Commentaire |
|---------|------|-------------|
| Architecture | 9/10 | Data Mapper ORM propre, modules solides, DI PSR-11, séparation claire des couches |
| Sécurité | 8/10 | Tous les fixes critiques appliqués, aucune vulnérabilité connue restante |
| Qualité du code | 8/10 | Typage fort, PHP 8.5, `AbstractModel` supprimé — reste singleton statique du Container |
| Documentation | 9/10 | README mis à jour (2300+ lignes), exemples concrets pour chaque composant |
| Complétude des fonctionnalités | 8/10 | ORM Data Mapper, multi-db, notifications, `#[IsGranted]` — manque seeding, pagination avancée, queues |
| Performance | 6/10 | Reflection intensive, scan complet en dev, pas de détection N+1 |

**Score global estimé : 48/60 (80 %)**

---

## Résumé exécutif

NeoPHP est un framework **bien architecturé, léger et moderne** qui tire parti des fonctionnalités PHP 8.5+ de manière cohérente. La réécriture complète de l'ORM en Data Mapper (juillet 2026) est un saut qualitatif majeur : `EntityManager`, `UnitOfWork`, `ProxyFactory`, relations complètes avec chargement lazy via proxies, workflow `make:entity` / `database:orm:diff` / migrations. L'`AbstractModel` Active Record a été entièrement supprimé.

Le support multi-base de données, les notifications (Email / Slack / SMS), l'attribut `#[IsGranted]`, les nouveaux middlewares (`CsrfMiddleware`, `AuthRateLimitMiddleware`) et les helpers de contrôleur (`getSession`, `getFlash`, `getCookie`) complètent la montée en version.

Le framework convient très bien à des **projets petits à moyens** (blog, backoffice, API REST, site vitrine) et commence à couvrir des besoins plus larges avec le multi-db et les notifications. Les prochains chantiers prioritaires sont le seeding, la pagination avancée, la validation avancée, et la couverture de tests du framework lui-même.

---

## Ordre de traitement recommandé

```
1. Module Testing — valider le scaffold en conditions réelles + EntityFactory
2. Tests du framework lui-même (ORM, Router, Container)
3. Remplacer singleton Container par injection via le kernel
4. DTOs et typage fort (AttributeScanResult, MiddlewareMeta, AbstractBlock, ListenerRegistration, etc.)
5. Database Seeding + Pagination avancée + Validation avancée (valeur produit directe)
6. API Resources + Versioning API (si usage API)
7. Cache avancé / Logging avancé / Stockage fichiers (selon besoins projet)
8. Queue / WebSockets / GraphQL (selon besoins projet avancés)
```
