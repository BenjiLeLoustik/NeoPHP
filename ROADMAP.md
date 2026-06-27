# ROADMAP — Suivi du framework NeoPHP

> Analyse complète du codebase (~387 fichiers PHP dans `neo/Core/`)
> Dernière mise à jour : 2026-06-27

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
├── Database/        → ORM, QueryBuilder, Migrations, Formulaires
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
├── Utils/           → Config, Cache, Logger, Mailer
├── Validator/       → Validation par contraintes attributs
└── View/            → Intégration Twig
```

---

## ✅ Ce qui est bon

### Architecture & Design
- **Système de modules** propre avec résolution des dépendances et auto-découverte
- **Routing par attributs PHP 8** (`#[Route]`, `#[MainRoute]`) — syntaxe propre et moderne
- **ORM complet** : 4 types de relations (HasOne, HasMany, BelongsTo, BelongsToMany), eager/lazy loading, identity map, soft deletes
- **Système d'événements** avec priorités sur les listeners, subscribers, cache en prod
- **Conteneur DI** conforme PSR-11 avec résolution par réflexion, détection des dépendances circulaires, codes d'erreur distincts (404/422/500)
- **Gestion des erreurs** différenciée : stack trace en dev, messages sûrs en prod
- **Système de formulaires** complet avec champs typés, CSRF, rendu Twig
- **Cron intégré** avec expressions cron standard, timezone et lock optionnel
- **CLI framework** intégré avec générateurs couvrant tout le workflow
- **Support Twig 3.x** avec extensions personnalisées, `twig/intl-extra`, cache de templates
- **Configuration** par fichiers PHP avec notation dot-notation
- **Migrations** avec pattern up/down, snapshots de schéma, détection de drift
- **Cache des routes** en production (JSON, plus de `unserialize()`)
- **CRUD generator** complet via `make:crud`
- **Génération ORM depuis le schéma** via `database:generate`

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

---

## ❌ Ce qui reste à adresser

### Sécurité résiduelle

| Problème | Localisation | Impact |
|----------|-------------|--------|
| **Désérialisation non sécurisée** — `unserialize()` toujours utilisé pour le cache des listeners | `EventDispatcher.php` l.54 | Critique |
| **Regex des requirements de routes** non échappées avant injection dans les patterns | `Router.php` | Moyen |
| **Pas de rate limiting** global configurable par route (hors auth) | Routing / Middleware | Moyen |

### Qualité du code résiduelle

| Problème | Localisation | Impact |
|----------|-------------|--------|
| **`@` operator** utilisé pour supprimer des erreurs à certains endroits | Divers | Faible |
| **Identity map statique** (`AbstractModel::$instanceCache`) — risque de données périmées entre requêtes CLI | `AbstractModel.php` | Moyen |
| **Singleton statique du Container** (`getInstance()`) — état global, problématique pour les tests | `Container.php` | Moyen |
| **`AbstractModel`** a trop de responsabilités (persistance + relations + identity map + soft delete) | `AbstractModel.php` | Moyen |

---

## 🗂 Checklist de suivi

> Statut : `[ ]` à faire — `[~]` en cours — `[x]` terminé — `[N]` Non pertinent pour le moment

---

### 🔴 URGENCES — Sécurité (à corriger avant toute mise en prod)

- [x] 🟢 **Échapper les messages d'exception** avec `htmlspecialchars()` dans `ErrorHandler.php`
- [x] 🟢 **Ajouter une limite de taille** sur `php://input` dans `Request.php` (8 Mo)
- [x] 🟢 **Valider les IPs** `X-Forwarded-For` avec `filter_var()` dans `Request.php`
- [x] 🟢 **Fix path traversal** — `realpath()` + vérification de préfixe dans `Request::sanitizePath()`
- [x] 🟡 **Quoter les identifiants SQL** avec des backticks dans `QueryBuilder.php`
- [x] 🟡 **Supprimer l'interpolation SQL directe** dans `AbstractModel.php` → identifiants quotés
- [x] 🟡 **Remplacer `unserialize()`** par `json_decode()` pour le cache de routes dans `Router.php`
- [x] 🟡 **Sécuriser l'inclusion dynamique** `require_once $filePath` dans `Router.php` — whitelist
- [x] 🟡 **Ajouter CSRF** sur les formulaires d'authentification dans `AuthManager.php`
- [x] 🟠 **Ajouter un timeout de session** dans `SessionGuard`
- [x] 🟠 **Sécuriser les uploads** — validation MIME, limite de taille, assainissement du nom
- [x] 🟠 **Rate limiting** sur les tentatives de connexion (Middleware dédié ou dans `AuthManager`)
- [ ] 🟡 **Remplacer `unserialize()`** par `json_decode()` pour le cache des listeners dans `EventDispatcher.php` l.54
- [ ] 🟢 **Échapper les requirements de routes** avant injection dans les patterns regex

---

### 🔧 Améliorations — Architecture & Qualité

- [x] 🟢 **Améliorer les codes d'erreur** dans le Container (404, 422, 500 distincts)
- [x] 🟡 **Supprimer `exit()`** dans `MiddlewareHandler.php` → retourner une Response propre
- [x] 🟡 **Uniformiser la gestion d'erreurs** — exceptions partout, supprimer les `false`/`null` implicites
- [x] 🟡 **Compiler les regex de routes une seule fois** et les mettre en cache
- [x] 🟠 **Ajouter un système d'ordre explicite** pour l'exécution des middlewares
- [N] 🟡 **Améliorer le typage** dans les zones utilisant `mixed` ou des tableaux non typés
- [ ] 🟠 **Remplacer le singleton statique du Container** par une injection via le kernel
- [ ] 🟠 **Invalider l'identity map** (`AbstractModel::$instanceCache`) après les mutations en CLI
- [ ] 🔴 **Séparer `AbstractModel`** en `Model` + `QueryScope` + `Relationships` (trop de responsabilités)

---

### ⚡ Améliorations — Performance

- [ ] 🟢 **Configurer les connexions PDO persistentes** en option dans `DatabaseConnection`
- [ ] 🟡 **Réduire l'usage de Reflection** — mettre les résultats en cache plus agressivement
- [ ] 🟡 **Contrôler le buffering de sortie** pour éviter les flushes prématurés
- [ ] 🟠 **Détection automatique des requêtes N+1** en mode dev (logguer les requêtes similaires répétées)
- [ ] 🟠 **Optimiser le scan de contrôleurs en dev** — cache avec watcher de fichiers plutôt que scan complet

---

### 🧪 Module Testing

- [ ] 🟡 **`TestCase` de base** avec container isolé (en cours — scaffold présent, container non isolé)
- [ ] 🟡 **`DatabaseTestCase`** avec transactions rollback automatique (scaffold présent, à valider en conditions réelles)
- [ ] 🟡 **`HttpTestCase`** — simulation de requêtes HTTP sans serveur (scaffold présent, à valider)
- [ ] 🟠 **`ModelFactory`** — générateur de données de test
- [ ] 🟡 **Tests du framework lui-même** — couverture minimale des composants critiques (QueryBuilder, Router, Container)

---

### 💡 Nouvelles fonctionnalités — Priorité haute

- [ ] 🟡 **Database Seeding** — Classes de seed + commande `database:seed`
- [ ] 🟠 **Validation avancée** — règles `unique:table`, `exists:table`, validation imbriquée, règles custom
- [ ] 🟠 **Classe `Paginator` standalone** — avec liens prev/next, métadonnées de page, rendu Twig intégré (le QueryBuilder a `paginate()` mais sans classe dédiée)
- [ ] 🟠 **API Resources** — transformateurs de réponse JSON (type Laravel Resource / Fractal)
- [ ] 🟠 **Versioning d'API** — support natif `/api/v1/`, `/api/v2/` dans le routeur

---

### 💡 Nouvelles fonctionnalités — Priorité moyenne

- [ ] 🟠 **Cache avancé** — driver APCu + amélioration du driver Redis (tags, flush par préfixe)
- [ ] 🟠 **Logging avancé** — handlers multiples simultanés (fichier + Slack + DB), rotation configurable par channel
- [ ] 🔴 **Stockage de fichiers** — abstraction disque local / S3 / cloud (type Flysystem)
- [x] 🔴 **Notifications** — Email, SMS, push via un système unifié
- [ ] 🔴 **Multi-base de données** — support de plusieurs connexions PDO simultanées dans l'ORM

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

> Évaluation à la date du 2026-06-27, après application des correctifs de sécurité critiques.

| Critère | Note | Commentaire |
|---------|------|-------------|
| Architecture | 8/10 | Modules propres, DI solide, séparation claire des couches |
| Sécurité | 7/10 | Fixes critiques appliqués ; reste `unserialize()` dans EventDispatcher et quelques points mineurs |
| Qualité du code | 7/10 | Typage fort, PHP 8.5, mais singleton statique et AbstractModel surchargé |
| Documentation | 8/10 | README très complet (2100+ lignes), exemples concrets pour chaque composant |
| Complétude des fonctionnalités | 7/10 | ORM, auth, forms, CLI, crons, assets — manque seeding, pagination avancée, queues |
| Performance | 6/10 | Reflection intensive, scan complet en dev, pas de détection N+1 |

**Score global estimé : 43/60 (72 %)**

---

## Résumé exécutif

NeoPHP est un framework **bien architecturé, léger et moderne** qui tire parti des fonctionnalités PHP 8.5+ de manière cohérente. La séparation des responsabilités est claire, le système de modules est solide, et l'ORM couvre l'essentiel des besoins courants.

Les correctifs de sécurité critiques ont été appliqués : injections SQL résolues, désérialisation PHP sécurisée dans le Router, CSRF sur l'auth, rate limiting, upload sécurisé, timeout de session. Il reste un point critique : `unserialize()` dans `EventDispatcher.php` pour le cache des listeners.

Le framework convient très bien à des **projets petits à moyens** (blog, backoffice, API REST, site vitrine). Pour des projets plus larges, les fonctionnalités manquantes (queue, stockage cloud, pagination avancée) et la couverture de tests du framework lui-même seraient les premiers chantiers à adresser.

---

## Ordre de traitement recommandé

```
1. Fix unserialize() dans EventDispatcher (sécurité critique résiduelle)
2. Fix regex requirements de routes non échappées
3. Module Testing — valider le scaffold en conditions réelles + ModelFactory
4. Tests du framework lui-même (QueryBuilder, Router, Container)
5. Remplacer singleton Container + invalider identity map CLI
6. Database Seeding + Pagination avancée + Validation avancée (valeur produit directe)
7. API Resources + Versioning API (si usage API)
8. Séparation AbstractModel (refacto structurant, breaking change)
9. Cache avancé / Logging avancé / Stockage fichiers (selon besoins projet)
10. Queue / WebSockets / GraphQL (selon besoins projet avancés)
```
