# NeoPHP

Framework PHP 8 orienté génération de projets, basé sur une CLI interne et un noyau applicatif contenu dans `neo/`.

Le dépôt courant correspond au moteur du framework. Les applications NeoPHP sont générées dans `src/<Projet>` via les commandes CLI, puis chargées dynamiquement par le runtime HTTP.

## Sommaire

- [Vue d'ensemble](#vue-densemble)
- [Philosophie du framework](#philosophie-du-framework)
- [Structure du dépôt](#structure-du-dépôt)
- [Comment NeoPHP choisit le projet courant](#comment-neophp-choisit-le-projet-courant)
- [Cycle d'exécution HTTP](#cycle-dexécution-http)
- [Conteneur et injection de dépendances](#conteneur-et-injection-de-dépendances)
- [Routing](#routing)
- [Middlewares](#middlewares)
- [Events](#events)
- [Vues Twig](#vues-twig)
- [Assets](#assets)
- [Base de données, ORM et formulaires](#base-de-données-orm-et-formulaires)
- [Authentification et sécurité](#authentification-et-sécurité)
- [Cache, logs, erreurs et environnement](#cache-logs-erreurs-et-environnement)
- [Commandes CLI](#commandes-cli)
- [Structure d'un projet généré](#structure-dun-projet-généré)
- [Configuration d'un projet](#configuration-dun-projet)
- [Démarrage rapide](#démarrage-rapide)
- [Versionning Git d'un projet NeoPHP](#versionning-git-dun-projet-neophp)
- [Dépendances Composer du framework](#dépendances-composer-du-framework)
- [Points à retenir](#points-à-retenir)

## Vue d'ensemble

NeoPHP repose sur deux points d'entrée :

- `public/index.php` pour le runtime web
- `bin/neo` pour la CLI

Le noyau `Neo\App` initialise :

- un conteneur d'injection de dépendances
- la requête HTTP ou une requête vide en CLI
- le chargement du projet courant
- les services coeur: Twig, assets, base de données, auth, events, middlewares, cache, logger, traductions
- le routeur basé sur des attributs PHP
- la gestion d'erreurs

## Philosophie du framework

NeoPHP n'est pas seulement un framework d'exécution. La CLI fait partie du design du projet :

- elle crée la structure des applications
- elle génère les contrôleurs, CRUD, middlewares, services, events et listeners
- elle prépare les fichiers de configuration
- elle gère le cache, les builds d'assets et certaines opérations de déploiement

En pratique, le framework se pilote principalement par `php bin/neo ...`.

## Structure du dépôt

```text
.
├── bin/
│   └── neo                      # point d'entrée CLI
├── neo/                         # coeur du framework
│   ├── App.php                  # bootstrap principal
│   └── Core/
│       ├── Asset/
│       ├── Console/
│       ├── Controller/
│       ├── Database/
│       ├── DI/
│       ├── Error/
│       ├── Event/
│       ├── Http/
│       ├── Routing/
│       ├── Security/
│       ├── Translation/
│       ├── Utils/
│       ├── Validator/
│       └── View/
├── public/
│   ├── index.php               # front controller HTTP
│   └── builds/                 # assets compilés par projet
├── src/                        # applications générées par la CLI
├── composer.json
└── vendor/
```

## Comment NeoPHP choisit le projet courant

### En HTTP

`Neo\App` cherche le projet actif dans `src/*/Config/app.config.php` via la clé :

- `access`

Le `SERVER_NAME` ou `HTTP_HOST` courant est comparé à cette valeur. Si un seul projet existe dans `src/`, il est utilisé automatiquement.

### En CLI

La CLI exige explicitement :

```bash
php bin/neo <commande> --project=NomDuProjet
```

Exception :

- `make:project` crée justement un nouveau projet, donc ne dépend pas d'un projet déjà existant

## Cycle d'exécution HTTP

1. `public/index.php` charge l'autoloader Composer.
2. `Neo\App` instancie le conteneur.
3. Le projet courant est résolu.
4. Les chemins applicatifs sont injectés dans le conteneur (`controllersPath`, `viewsPath`, `storagePath`, etc.).
5. Les services coeur sont enregistrés.
6. Le routeur scanne `App/Controllers` et lit les attributs `#[MainRoute]` et `#[Route]`.
7. Les listeners d'events sont scannés dans `App/Event/Listener`.
8. Une `RequestEvent` est dispatchée.
9. Le routeur matche l'URL, exécute les middlewares, invoque le contrôleur, puis produit une `Response`.
10. Une `ResponseEvent` est dispatchée.
11. La réponse est envoyée au client.

## Conteneur et injection de dépendances

Le conteneur `Neo\Core\DI\Container` supporte :

- `set()` pour enregistrer une définition
- `bind()` pour associer un abstrait à un concret
- `instance()` pour injecter un objet déjà instancié
- `make()` pour construire une classe avec paramètres
- autowiring par réflexion

Les contrôleurs héritant de `AbstractController` reçoivent automatiquement :

- `Request`
- `Response`
- `View`
- `MiddlewareHandler`

Et, via des helpers, l'accès à :

- session, cookies, flash
- auth
- cache
- logger
- config
- router
- dispatcher d'events
- gestionnaire de mots de passe

## Routing

Le routing repose sur des attributs PHP :

```php
#[MainRoute(path: '/admin/users', name: 'admin.users')]
final class UserController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/admin/users/index.html.twig');
    }
}
```

Points importants :

- les contrôleurs sont scannés automatiquement dans `src/<Projet>/App/Controllers`
- les routes sont mises en cache hors environnement `dev`
- les paramètres dynamiques `{id}` et optionnels `{slug?}` sont supportés
- des contraintes regex peuvent être déclarées dans `#[Route(..., requirements: [...])]`
- la fonction Twig `path()` génère les URLs nommées
- la fonction Twig `currentRoute()` expose la route courante

## Middlewares

Les middlewares sont déclarés par attribut :

```php
#[Middleware(use: AuthMiddleware::class, redirect: 'login.index', message: 'Connexion requise')]
```

ou :

```php
#[RateLimit(maxAttempts: 10, decaySeconds: 60)]
```

Le `MiddlewareHandler` exécute les middlewares au niveau :

- de la classe contrôleur
- de la méthode d'action

Comportements pris en charge :

- blocage direct avec exception 403
- mode soft avec flash warning
- redirection automatique vers une route nommée

Middlewares fournis par défaut :

- `AuthMiddleware`
- `GuestMiddleware`
- `RoleMiddleware`
- `RateLimitMiddleware`

## Events

NeoPHP embarque un système d'events avec :

- `EventDispatcher`
- `AbstractEvent`
- `#[AsListener]`
- `EventSubscriberInterface`

Les listeners sont scannés automatiquement dans :

- `src/<Projet>/App/Event/Listener`

Les événements coeur dispatchés par l'application sont :

- `RequestEvent`
- `ResponseEvent`
- `ExceptionEvent`

## Vues Twig

Le moteur de vues repose sur Twig et charge les templates depuis :

- `src/<Projet>/App/Views`

Fonctions Twig injectées par le framework :

- `path()`
- `currentRoute()`
- `asset()`
- `auth_check()`
- `auth_user()`
- `auth_has_role()`
- `csrf_token()`
- fonctions liées aux formulaires
- fonctions liées aux traductions

Le projet généré par défaut crée :

- `layouts/base_layout.html.twig`
- `pages/default/index.html.twig`

## Assets

Le gestionnaire d'assets compile depuis :

- `src/<Projet>/Assets`

Vers :

- `public/builds/<Projet>/assets`

Fonctionnement :

- en `dev`, l'asset est compilé à la demande si le hash change
- en `prod`, le framework lit `public/builds/<Projet>/manifest.json`
- les fichiers générés sont versionnés par hash
- les CSS et JS sont minifiés
- les fichiers `.less` sont compilés en CSS

Exemple Twig :

```twig
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<script src="{{ asset('js/app.js') }}"></script>
```

## Base de données, ORM et formulaires

Le service `DatabaseConnection` ouvre la connexion PDO si `database.config.php` active la base.

Ensuite, NeoPHP lance automatiquement l'ORM :

- introspection des tables
- génération des modèles
- génération des repositories
- génération des formulaires

Le coeur ORM comprend :

- `AbstractModel`
- relations via attributs `HasOne`, `HasMany`, `BelongsTo`, `BelongsToMany`
- identity map
- repositories dédiés

Le moteur de formulaires fournit :

- gestion des champs
- binding sur modèle
- validation
- CSRF
- rendu Twig via `form_start`, `form_row`, `form_widget`, `form_end`, etc.

## Traduction

Les traductions sont stockées dans :

- `src/<Projet>/Translations/<locale>/<fichier>.php`

Exemple de clé :

```php
'welcome.header.title'
```

Le `TranslationManager` :

- résout la locale via la config et le cookie
- traduit les clés dans Twig
- peut auto-enregistrer les clés manquantes en environnement `dev`

Le projet généré par défaut crée :

- `Translations/fr/welcome.php`
- `Translations/en/welcome.php`

## Authentification

L'auth est pilotée par `app.config.php`.

Configuration attendue :

- `auth.enabled`
- `auth.model`
- `auth.identifier`
- `auth.password`
- `auth.role`

Le guard fourni est un guard de session (`SessionGuard`) basé sur :

- une session PHP
- un modèle ORM
- un mot de passe hashé via `PasswordManager`

Fonctions Twig disponibles :

- `auth_check()`
- `auth_user()`
- `auth_has_role()`

## Gestion des erreurs

`ErrorHandler` :

- enregistre les erreurs et exceptions
- écrit dans les logs framework
- dispatch un `ExceptionEvent`
- rend une vue `errors/<code>.html.twig` si elle existe
- sinon affiche une page HTML de fallback

En `dev`, les détails techniques sont affichés. En `prod`, le message est volontairement masqué.

## Commandes CLI

La CLI scanne automatiquement `neo/Core/Console/Commands` et expose les classes annotées avec `#[Command]`.

Afficher l'aide globale :

```bash
php bin/neo
```

Afficher l'aide d'une commande :

```bash
php bin/neo <commande> --help
```

### 1. `make:project`

Crée une nouvelle application dans `src/`.

```bash
php bin/neo make:project Blog
php bin/neo make:project Backoffice --skeleton
```

Ce que la commande génère :

- `App/Controllers`
- `App/Middlewares`
- `App/Services`
- `App/Views`
- `App/Forms`
- `Assets`
- `Config`
- `Model`
- `Repository`
- `Storage`
- `Translations`
- un `.gitignore` dans le dossier du projet

En mode complet, elle ajoute aussi :

- un `DefaultController`
- un layout Twig
- une page d'accueil
- des assets CSS/JS par défaut
- des traductions FR/EN
- tous les fichiers de config de base
- un `composer.json` dans le projet
- une entrée `repositories` + `require` dans le `composer.json` racine
- un `composer update`

Le `app.config.php` généré définit aussi un `access` de type `localhost:8000`, `localhost:8001`, etc. pour éviter les collisions entre projets.

Après la création du projet, lancez aussi :

```bash
php bin/neo generate:default:config --project=Blog
```

Cette commande génère les fichiers sensibles du projet :

- `src/Blog/Config/api.config.php`
- `src/Blog/Config/deploy.config.php`
- `src/Blog/Config/database.config.php`

Ces fichiers sont ignorés automatiquement par le `.gitignore` du projet.

### 2. `make:controller`

Crée un contrôleur web ou API.

```bash
php bin/neo make:controller UserController --project=Blog
php bin/neo make:controller AdminUser --dir Admin --project=Blog
php bin/neo make:controller ApiUser --api --project=Blog
```

Effets :

- création du contrôleur dans `App/Controllers`
- ajout automatique des attributs `#[MainRoute]` et `#[Route]`
- génération d'une vue Twig `pages/.../index.html.twig` si le contrôleur n'est pas en mode API

### 3. `make:crud`

Génère un squelette CRUD complet pour une entité.

```bash
php bin/neo make:crud User --project=Blog
php bin/neo make:crud Product --dir Admin --force --project=Blog
```

Effets :

- création d'un contrôleur `UserController`
- génération des routes :
  - `index`
  - `show`
  - `create`
  - `edit`
  - `delete`
- génération des vues :
  - `index.html.twig`
  - `show.html.twig`
  - `create.html.twig`
  - `edit.html.twig`

### 4. `make:config`

Crée un fichier de configuration interactif :

```bash
php bin/neo make:config mail --project=Blog
```

Effets :

- génération de `src/Blog/Config/mail.config.php`
- saisie interactive clé/valeur
- support de notation pointée comme `ftp.host` ou `remote.domain`

### 5. `make:service`

Crée une classe de service :

```bash
php bin/neo make:service Mail --project=Blog
php bin/neo make:service Mail --dir Utils --project=Blog
```

Génère :

- `src/<Projet>/App/Services/<...>/MailService.php`

### 6. `make:middleware`

Crée un middleware personnalisé :

```bash
php bin/neo make:middleware Auth --project=Blog
php bin/neo make:middleware AdminAccess --dir Security --project=Blog
```

Génère :

- `src/<Projet>/App/Middlewares/<...>/AuthMiddleware.php`

Le squelette implémente `MiddlewareInterface`.

### 7. `make:event`

Crée un événement :

```bash
php bin/neo make:event UserRegistered --project=Blog
```

Génère :

- `src/<Projet>/App/Event/UserRegisteredEvent.php`

Le squelette étend `AbstractEvent`.

### 8. `make:event:listener`

Crée un listener pour un event :

```bash
php bin/neo make:event:listener SendWelcomeEmail --event=UserRegistered --project=Blog
php bin/neo make:event:listener SyncCRM --event=UserRegistered --priority=10 --project=Blog
```

Génère :

- `src/<Projet>/App/Event/Listener/SendWelcomeEmailListener.php`

Le squelette contient :

- l'attribut `#[AsListener(...)]`
- une méthode `handle()`
- une priorité optionnelle

### 9. `composer:require`

Ajoute une dépendance Composer à un projet précis :

```bash
php bin/neo composer:require stripe/stripe-php --project=Blog
php bin/neo composer:require symfony/mailer ^7.0 --project=Blog
```

Effets :

- mise à jour de `src/<Projet>/composer.json`
- vérification de la dépendance existante
- exécution d'un `composer update`

### 10. `cache:clear`

Vide le cache du projet :

```bash
php bin/neo cache:clear --project=Blog
```

Cible :

- `src/<Projet>/Storage/var/cache`

### 11. `asset:reload`

Supprime complètement les builds d'assets :

```bash
php bin/neo asset:reload --project=Blog
```

Cible :

- `public/builds/<Projet>`

### 12. `delete:project`

Supprime un projet NeoPHP après confirmation interactive :

```bash
php bin/neo delete:project --project=Blog
```

Effets :

- suppression des builds
- nettoyage du `composer.json` racine
- suppression du dossier `src/<Projet>`
- exécution d'un `composer update`

### 13. `make:deployment`

Commande de déploiement FTP :

```bash
php bin/neo make:deployment --project=Blog
```

Pré-requis :

- `src/<Projet>/Config/deploy.config.php` renseigné

Cette commande :

- bascule temporairement `app.config.php` en mode production
- fusionne les `composer.json`
- lance `composer update`
- compresse `vendor/`
- envoie les fichiers via FTP
- téléverse un script de décompression côté serveur
- exécute la phase finale de déploiement

## Structure d'un projet généré

```text
src/Blog/
├── App/
│   ├── Controllers/
│   ├── Event/
│   │   └── Listener/
│   ├── Forms/
│   ├── Middlewares/
│   ├── Services/
│   └── Views/
│       ├── errors/
│       ├── layouts/
│       ├── pages/
│       └── partials/
├── Assets/
│   ├── css/
│   └── js/
├── Config/
│   ├── app.config.php
│   ├── cache.config.php
│   ├── database.config.php
│   ├── deploy.config.php
│   ├── logger.config.php
│   ├── session.config.php
│   └── twig.config.php
├── Model/
├── Repository/
├── Storage/
└── Translations/
    ├── en/
    └── fr/
```

## Fichiers de configuration générés

### `app.config.php`

Contient notamment :

- `general.name`
- `general.description`
- `environment`
- `access`
- `date.timezone`
- `translation.*`
- `auth.*`

### `database.config.php`

Prépare une connexion PDO désactivée par défaut :

- `enabled => false`
- `use => "default"`
- `connections.default.driver => mysql`

### `deploy.config.php`

Prépare le déploiement FTP :

- `ftp.host`
- `ftp.user`
- `ftp.pass`
- `remote.domain`
- `remote.framework_dir`
- `remote.public_dir`

### `logger.config.php`

Prépare :

- canaux de logs
- rotation quotidienne ou par taille
- archivage ZIP

### `cache.config.php`

Prépare un cache fichier avec TTL.

### `twig.config.php`

Configure :

- cache
- debug
- auto reload
- auto escape
- charset

### `session.config.php`

Configure :

- session PHP
- stockage des sessions
- cookies
- flash messages

## Démarrage rapide

### Flux complet

Depuis un terminal, clonez d'abord le framework :

```bash
git clone https://github.com/BenjiLeLoustik/NeoPHP.git
cd NeoPHP
composer install
```

Créez ensuite le projet depuis la racine du framework :

```bash
php bin/neo make:project Blog
php bin/neo generate:default:config --project=Blog
```

Le projet créé contient automatiquement un `.gitignore` local. Il ignore notamment les fichiers sensibles générés ensuite par `generate:default:config`.

Créez maintenant le dépôt Git du projet, cette fois dans le dossier du projet :

```bash
cd src/Blog
git init
git add .
git commit -m "Initial commit"
git branch -M main
git remote add origin <url-du-repo-projet>
git push -u origin main
cd ../..
```

Pour afficher le site, revenez à la racine du framework puis lancez le serveur PHP intégré :

```bash
php -S localhost:8000 -t public
```

Ensuite, ouvrez dans le navigateur :

```text
http://localhost:8000
```

Important :

- la valeur utilisée dans `php -S <access>:<port> -t public` doit correspondre à l'accès configuré pour le projet
- le `app.config.php` généré définit un `access` du type `localhost:8000`, `localhost:8001`, etc.
- si votre projet est configuré avec un autre accès, adaptez la commande en conséquence
- toutes les commandes NeoPHP se lancent à la racine du framework
- toutes les commandes Git du projet se lancent dans `src/Blog`

## Versionning Git d'un projet NeoPHP

- le dépôt du framework et le dépôt du projet sont distincts
- commencez par faire un `git clone` du framework NeoPHP pour pouvoir créer et exécuter un projet
- toutes les commandes CLI NeoPHP se lancent à la racine du framework
- toutes les commandes Git du projet se lancent dans le dossier du projet, par exemple `src/Blog`
- le repository Git du projet ne contient que la structure du projet, pas le coeur du framework

Exemple de flux :

1. Cloner le framework NeoPHP.
2. Depuis la racine du framework, créer le projet avec `php bin/neo make:project Blog`.
3. Depuis la racine du framework, générer les configs sensibles avec `php bin/neo generate:default:config --project=Blog`.
4. Aller dans `src/Blog`, initialiser le dépôt Git du projet, puis faire les commandes `git init`, `git add`, `git commit`, `git remote add`, `git push`, etc.

### Créer un contrôleur

```bash
php bin/neo make:controller Post --project=Blog
```

### Créer un CRUD

```bash
php bin/neo make:crud Post --project=Blog
```

### Ajouter une config métier

```bash
php bin/neo make:config mail --project=Blog
```

### Vider le cache

```bash
php bin/neo cache:clear --project=Blog
```

## Dépendances Composer du framework

Le coeur utilise notamment :

- `twig/twig`
- `twig/intl-extra`
- `psr/container`
- `matthiasmullie/minify`
- `wikimedia/less.php`

## Points à retenir

- `neo/` contient le framework
- `src/` contient les applications générées
- le routing, les middlewares et les listeners sont basés sur le scan de fichiers + attributs PHP
- la CLI est la porte d'entrée principale pour produire l'architecture d'un projet
- chaque projet est isolé par sa config, son espace de stockage, ses vues, ses assets et ses builds
