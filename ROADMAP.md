# RECAP — Suivi du framework NeoPHP

> Analyse complète du codebase (266 fichiers PHP)
> Dernière mise à jour : 2026-06-16

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
├── Asset/           → Compilation CSS/JS
├── Console/         → Framework CLI
├── Controller/      → Contrôleur de base & extensions
├── Cron/            → Tâches planifiées
├── Database/        → ORM, QueryBuilder, Migrations
├── DI/              → Conteneur d'injection de dépendances
├── Error/           → Gestion des erreurs & exceptions
├── Event/           → Système d'événements & dispatcher
├── Extension/       → Système d'extensions
├── Http/            → Request / Response
├── Module/          → Système de modules
├── Profiler/        → Profilage des performances
├── Routing/         → Routeur & routing par attributs
├── Security/        → Auth, CSRF, Middleware
├── Testing/         → Utilitaires de test
├── Translation/     → Support i18n
├── Utils/           → Config, Cache, Logger, Mailer
├── Validator/       → Validation des formulaires
└── View/            → Moteur Twig
```

---

## ✅ Ce qui est bon

### Architecture & Design
- **Système de modules** propre avec résolution des dépendances et auto-découverte
- **Routing par attributs PHP 8** (`#[Route]`) — syntaxe propre et moderne
- **ORM complet** : 4 types de relations (HasOne, HasMany, BelongsTo, BelongsToMany), eager loading, identity map, soft deletes
- **Système d'événements** avec priorités sur les listeners
- **Conteneur DI** conforme PSR-11 avec résolution automatique par réflexion, détection des dépendances circulaires, support des tags
- **Gestion des erreurs** différenciée : détails en dev, messages sûrs en prod
- **Système de formulaires** avec génération de champs typés
- **Cron intégré** avec parsing d'expressions cron standard
- **CLI framework** intégré avec commandes
- **Support Twig** complet avec extensions personnalisées et cache de templates
- **Configuration** en notation dot-notation imbriquée
- **Migrations** avec pattern up/down
- **Cache de routes** en production

### Qualité du code
- Usage cohérent des **fonctionnalités PHP 8+** : attributs, propriétés typées, union types, named arguments
- **Typage fort** : la grande majorité des méthodes a des types de retour et de paramètres
- **Respect PSR-4** pour l'autoloading
- **Docblocks** présents sur la majorité des méthodes (`@param`, `@return`, `@throws`)
- **Nommage clair** et méthodes descriptives
- **QueryBuilder** avec binding de paramètres
- Hachage des mots de passe en **Argon2/bcrypt** (cost=12)
- **Régénération de session** à la connexion
- `hash_equals()` utilisé correctement pour la comparaison CSRF

---

## ❌ Ce qui n'est pas bon du tout

### Sécurité critique

| Problème | Localisation | Impact |
|----------|-------------|--------|
| **Injection SQL** — noms de tables/colonnes non quotés dans le QueryBuilder | `QueryBuilder.php` l.56, 77, 101-112 | Critique |
| **Injection SQL** — interpolation de chaînes directe dans les queries ORM | `AbstractModel.php` l.314-360 | Critique |
| **Désérialisation non sécurisée** — `unserialize()` sur le cache de routes et listeners | `Router.php` l.56 | Critique |
| **Inclusion de fichiers dynamique** — `require_once $filePath` avec chemins non validés | `Router.php` l.87 | Critique |
| **Path traversal** — `sanitizePath()` ne filtre pas `..` | `Request.php` l.108 | Élevé |
| **Usurpation d'IP** — `X-Forwarded-For` accepté sans validation | `Request.php` l.281-298 | Élevé |
| **Injection HTML** dans les pages d'erreur — message d'exception non échappé | `ErrorHandler.php` l.176-191 | Élevé |
| **Pas de CSRF** sur les formulaires d'authentification | `AuthManager.php` | Élevé |
| **Pas de limite de taille** sur `php://input` — épuisement mémoire possible | `Request.php` l.65 | Élevé |
| **Pas de rate limiting** sur les tentatives de connexion | Auth global | Élevé |
| **Pas de timeout de session** | `SessionGuard` | Moyen |
| **Upload de fichiers** sans validation MIME, sans limite de taille, sans assainissement du nom | Upload | Moyen |

### Qualité du code

- **`exit` dans le middleware** (`MiddlewareHandler.php` l.119) — arrêt brutal, impossible à tester unitairement
- **Singleton statique** dans le Container (`getInstance()`) — état global, problématique pour les tests et le CLI
- **Identity map statique** (`AbstractModel::$instanceCache`) — risque de données périmées entre requêtes CLI
- **Gestion d'erreurs mixte** — certains chemins lèvent des exceptions, d'autres retournent `false`/`null` sans cohérence
- **`@` operator** utilisé pour supprimer des erreurs au lieu de les gérer proprement
- **Tous les codes d'erreur à 500** dans le Container — aucune distinction entre les types d'erreur
- **Regex des routes non échappées** — les requirements sont insérés directement dans des patterns regex

---

## 🗂 Checklist de suivi

> Statut : `[ ]` à faire — `[~]` en cours — `[x]` terminé

### 🔴 URGENCES — Sécurité (à corriger avant toute mise en prod)

- [x] 🟢 **Échapper les messages d'exception** avec `htmlspecialchars()` dans `ErrorHandler.php` l.176-191 `branch: fix/xss-escape-exception-messages`
- [x] 🟢 **Ajouter une limite de taille** sur `php://input` dans `Request.php` l.65 (ex : 8 Mo) `branch: fix/request-input-size-limit`
- [x] 🟢 **Valider les IPs** `X-Forwarded-For` avec `filter_var()` dans `Request.php` l.281-298 `branch: fix/validate-x-forwarded-for-ip`
- [x] 🟢 **Fix path traversal** — ajouter `realpath()` + vérification de préfixe dans `Request::sanitizePath()` `branch: fix/path-traversal-sanitize-path`
- [x] 🟡 **Quoter les identifiants SQL** avec des backticks dans `QueryBuilder.php` l.56, 77, 101-112 `branch: fix/sql-quote-identifiers-backticks`
- [x] 🟡 **Supprimer l'interpolation SQL directe** dans `AbstractModel.php` l.314-360 → utiliser des identifiants quotés `branch: fix/sql-remove-direct-interpolation`
- [x] 🟡 **Remplacer `unserialize()`** par `json_decode()` pour le cache de routes dans `Router.php` l.56 `branch: fix/router-replace-unserialize-cache`
- [x] 🟡 **Sécuriser l'inclusion dynamique** `require_once $filePath` dans `Router.php` l.87 — valider la whitelist `branch: fix/router-dynamic-include-whitelist`
- [x] 🟡 **Ajouter CSRF** sur les formulaires d'authentification dans `AuthManager.php` `branch: feature/auth-csrf-protection`
- [x] 🟠 **Ajouter un timeout de session** dans `SessionGuard` `branch: feature/session-timeout`
- [x] 🟠 **Sécuriser les uploads** — validation MIME, limite de taille, assainissement du nom de fichier `branch: feature/secure-file-uploads`
- [x] 🟠 **Rate limiting** sur les tentatives de connexion (Middleware dédié ou dans `AuthManager`) `branch: feature/auth-rate-limiting`

---

### 🔧 Améliorations — Architecture & Qualité

- [x] 🟢 **Améliorer les codes d'erreur** dans le Container (404, 422, 500 distincts)  `branch: refactor/container-distinct-error-codes`
- [x] 🟢 **Échapper les requirements de routes** avant injection dans les regex  `branch: fix/router-escape-route-requirements`
- [x] 🟡 **Supprimer `exit()`** dans `MiddlewareHandler.php` l.119 → retourner une Response propre  `branch: refactor/middleware-remove-exit-call`
- [x] 🟡 **Uniformiser la gestion d'erreurs** — exceptions partout, supprimer les `false`/`null` implicites  `branch: refactor/uniform-error-handling`
- [x] 🟡 **Compiler les regex de routes une seule fois** et les mettre en cache  `branch: refactor/router-cache-compiled-regex`
- [ ] 🟡 **Améliorer le typage** dans les zones utilisant `mixed` ou des tableaux non typés  `branch: refactor/improve-mixed-type-hints`
- [x] 🟠 **Remplacer le singleton statique du Container** par une injection via le kernel  `branch: refactor/container-remove-static-singleton`
- [x] 🟠 **Invalider l'identity map** (`AbstractModel::$instanceCache`) après les mutations en CLI  `branch: fix/model-invalidate-identity-map-cli`
- [x] 🟠 **Ajouter un système d'ordre explicite** pour l'exécution des middlewares  `branch: feature/middleware-explicit-order`
- [x] 🔴 **Séparer `AbstractModel`** en `Model` + `QueryScope` + `Relationships` (trop de responsabilités)  `branch: breaking/split-abstract-model`

---

### ⚡ Améliorations — Performance

- [ ] 🟢 **Configurer les connexions PDO persistentes** en option dans `DatabaseConnection` `branch: feature/pdo-persistent-connections`
- [ ] 🟡 **Réduire l'usage de Reflection** — mettre les résultats en cache plus agressivement `branch: refactor/cache-reflection-results`
- [ ] 🟡 **Contrôler le buffering de sortie** pour éviter les flushes prématurés `branch: fix/output-buffering-control`
- [ ] 🟠 **Détection automatique des requêtes N+1** en mode dev (logguer les requêtes similaires répétées) `branch: feature/dev-nplusone-detection`
- [ ] 🟠 **Optimiser le scan de contrôleurs en dev** — cache avec watcher de fichiers plutôt que scan complet `branch: refactor/dev-controller-scan-cache`

---

### 🧪 Module Testing (actuellement vide)

- [ ] 🟡 **`TestCase` de base** avec container isolé `branch: test/base-testcase-isolated-container`
- [ ] 🟡 **`DatabaseTestCase`** avec transactions rollback automatique `branch: test/database-testcase-rollback`
- [ ] 🟡 **`HttpTestCase`** pour simuler des requêtes HTTP sans serveur `branch: test/http-testcase-request-simulator`
- [ ] 🟠 **`ModelFactory`** — générateur de données pour les tests `branch: test/model-factory`

---

### 💡 Nouvelles fonctionnalités — Priorité haute

- [ ] 🟡 **Database Seeding** — Classes de seed + commande `neo db:seed` `branch: feature/db-seeding`
- [ ] 🟠 **Validation avancée** — règles `unique:table`, `exists:table`, validation imbriquée, règles custom `branch: feature/validation-advanced-rules`
- [ ] 🟠 **Pagination** — classe `Paginator` intégrée au QueryBuilder avec liens prev/next `branch: feature/paginator`
- [ ] 🟠 **API Resources** — transformateurs de réponse JSON (type Laravel Resource / Fractal) `branch: feature/api-resources-transformer`
- [ ] 🔴 **Rate Limiting** — Middleware intégré avec backend Redis ou APCu `branch: feature/rate-limiting-middleware`

---

### 💡 Nouvelles fonctionnalités — Priorité moyenne

- [ ] 🟠 **Scaffold CLI** — générateurs `neo make:controller`, `neo make:model`, `neo make:migration`, `neo make:middleware` `branch: feature/cli-scaffold-generators`
- [ ] 🟠 **Cache avancé** — drivers Redis, Memcached, APCu (actuellement array uniquement) `branch: feature/cache-redis-memcached-apcu`
- [ ] 🟠 **Logging avancé** — intégration Monolog, niveaux PSR-3, handlers multiples (fichier, Slack, DB…) `branch: feature/logging-monolog-psr3`
- [ ] 🟠 **Versioning d'API** — support natif `/api/v1/`, `/api/v2/` dans le routeur `branch: feature/api-versioning`
- [ ] 🔴 **Stockage de fichiers** — abstraction disque local / S3 / cloud `branch: feature/file-storage-abstraction`
- [ ] 🔴 **Notifications** — Email, SMS, push via un système unifié `branch: feature/notifications-unified`
- [ ] 🔴 **Multi-base de données** — support de plusieurs connexions PDO simultanées `branch: feature/multi-database-connections`

---

### 💡 Nouvelles fonctionnalités — Priorité basse / Nice to have

- [ ] 🟠 **Health check endpoint** — route `/health` native pour les déploiements containerisés `branch: feature/health-check-endpoint`
- [ ] 🟠 **Multi-langue dans les routes** — `/fr/produits/{id}` et `/en/products/{id}` `branch: feature/i18n-route-localization`
- [ ] 🔴 **Queue / Jobs** — système de files de traitement asynchrone `branch: feature/async-job-queue`
- [ ] 🔴 **Debugbar** — toolbar de debug plus riche que le Profiler actuel (type Symfony) `branch: feature/debugbar-toolbar`
- [ ] 🔴 **Multi-tenant** — routing et DB par tenant `branch: feature/multi-tenant`
- [ ] 🔴 **GraphQL** — couche GraphQL en complément du REST `branch: feature/graphql-layer`
- [ ] 🔴 **WebSockets** — support Swoole/Ratchet pour le temps réel `branch: feature/websockets-realtime`

---

## Scores globaux

| Critère | Note |
|---------|------|
| Architecture | 8/10 |
| Sécurité | 5/10 |
| Qualité du code | 7/10 |
| Documentation | 6/10 |
| Complétude des fonctionnalités | 6/10 |
| Performance | 7/10 |

---

## Résumé exécutif

NeoPHP est un framework **bien architecturé, léger et moderne** qui tire parti des fonctionnalités PHP 8+ de manière cohérente. La séparation des responsabilités est claire, le système de modules est solide, et l'ORM couvre l'essentiel des besoins.

Les points critiques à corriger avant toute mise en production concernent exclusivement la **sécurité** : injection SQL dans le QueryBuilder, désérialisation PHP non sécurisée, et absence de rate limiting sur l'authentification. Ces issues sont localisées et corrigeables rapidement.

Le framework convient très bien à des **projets petits à moyens**. Pour des projets plus larges, les fonctionnalités manquantes (queue, stockage, pagination avancée) et l'absence d'utilitaires de test réels seraient les premiers chantiers à adresser.

---

## Ordre de traitement recommandé

```
1. Urgences sécurité (🟢 faciles en premier → gain immédiat, risque zéro)
2. Fix exit() middleware + uniformisation erreurs (débloque les tests)
3. Module Testing (nécessaire avant tout refacto sérieux)
4. Séparation AbstractModel + singleton Container (refacto structurant)
5. Database Seeding + Validation avancée + Pagination (valeur produit directe)
6. Scaffold CLI (productivité développeur)
7. Cache / Logging / Stockage (selon besoins projet)
8. Queue / WebSockets / GraphQL (selon besoins projet avancés)
```
