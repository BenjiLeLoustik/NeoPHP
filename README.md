# NeoPHP

Framework PHP 8 oriente generation de projets, construit autour d'une CLI interne et d'un noyau applicatif dans `neo/`.

Le depot courant contient le moteur du framework. Les applications generees vivent dans `src/<Projet>`.

## Sommaire

- [Vue d'ensemble](#vue-densemble)
- [Structure du depot](#structure-du-depot)
- [Explication rapide du framework](#explication-rapide-du-framework)
- [Commandes CLI](#commandes-cli)
- [Demarrage rapide](#demarrage-rapide)
- [Base de donnees, ORM, formulaires, auth et traductions](#base-de-donnees-orm-formulaires-auth-et-traductions)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Tests PHPUnit](#tests-phpunit)
- [Dependances Composer](#dependances-composer)
- [Points a retenir](#points-a-retenir)

## Vue d'ensemble

NeoPHP repose sur deux points d'entree :

- `public/index.php` pour le runtime HTTP
- `bin/neo` pour la CLI

Le noyau `Neo\App` se charge de :

- resoudre le projet courant
- initialiser le conteneur de dependances
- charger la configuration du projet
- enregistrer les services coeur
- scanner les routes, middlewares et listeners
- executer la requete HTTP ou la commande CLI
- gerer les erreurs

En pratique, NeoPHP se pilote surtout avec `php bin/neo ...`.

## Structure du depot

```text
.
|-- bin/
|   `-- neo
|-- neo/
|   |-- App.php
|   `-- Core/
|       |-- Console/
|       |-- Controller/
|       |-- Database/
|       |-- DI/
|       |-- Error/
|       |-- Event/
|       |-- Http/
|       |-- Routing/
|       |-- Security/
|       |-- Testing/
|       `-- View/
|-- public/
|   |-- index.php
|   `-- builds/
|-- src/
|   `-- <Projet>/
|       |-- App/
|       |-- Assets/
|       |-- Config/
|       |-- Model/
|       |-- Repository/
|       |-- Storage/
|       |-- Tests/
|       `-- Translations/
|-- composer.json
`-- vendor/
```

## Explication rapide du framework

### Resolution du projet

En HTTP, NeoPHP lit `src/<Projet>/Config/app.config.php` et compare la cle `access` avec `HTTP_HOST` ou `SERVER_NAME`.

En CLI, on cible explicitement un projet :

```bash
php bin/neo <commande> --project=Blog
```

### Routing

Le routing repose sur des attributs PHP scannes automatiquement dans `src/<Projet>/App/Controllers`.

```php
#[MainRoute(path: '/posts', name: 'posts')]
final class PostController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/posts/index.html.twig');
    }
}
```

Points utiles :

- routes nommees
- parametres dynamiques `{id}` et optionnels `{slug?}`
- cache des routes hors environnement `dev`
- fonctions Twig `path()` et `currentRoute()`

### Middlewares et events

Les middlewares se declarent par attribut, par exemple :

```php
#[Middleware(use: AuthMiddleware::class, redirect: 'login.index')]
```

Ou avec rate limit :

```php
#[RateLimit(maxAttempts: 10, decaySeconds: 60)]
```

Les listeners d'evenements sont scannes dans `src/<Projet>/App/Event/Listener`.

### Vues et assets

Les vues Twig sont chargees depuis `src/<Projet>/App/Views`.

Fonctions Twig utiles :

- `path()`
- `asset()`
- `currentRoute()`
- `auth_check()`
- `auth_user()`
- `auth_has_role()`
- `csrf_token()`
- helpers formulaires
- helpers de traduction via `trans()` et `translate()`

Les assets sont compiles depuis `src/<Projet>/Assets` vers `public/builds/<Projet>/assets`.

## Commandes CLI

Afficher l'aide globale :

```bash
php bin/neo
```

Afficher l'aide d'une commande :

```bash
php bin/neo <commande> --help
```

Commandes principales :

- `make:project` : creer un projet dans `src/`
- `generate:default:config` : generer les fichiers sensibles du projet
- `make:controller` : creer un controleur
- `make:crud` : generer un CRUD complet
- `make:service` : creer un service
- `make:middleware` : creer un middleware
- `make:event` : creer un evenement
- `make:event:listener` : creer un listener
- `make:config` : creer une config metier
- `composer:require` : ajouter une dependance a un projet
- `cache:clear` : vider le cache du projet
- `asset:reload` : regenerer les builds d'assets
- `delete:project` : supprimer un projet
- `make:deployment` : preparer un deploiement FTP
- `make:test` : generer un test PHPUnit
- `run:test` : lancer un test precis
- `run:test:all` : lancer tous les tests du projet

Exemples rapides :

```bash
php bin/neo make:project Blog
php bin/neo generate:default:config --project=Blog
php bin/neo make:controller PostController --project=Blog
php bin/neo make:crud User --project=Blog
php bin/neo cache:clear --project=Blog
```

## Demarrage rapide

### 1. Installer le framework

```bash
git clone https://github.com/BenjiLeLoustik/NeoPHP.git
cd NeoPHP
composer install
```

### 2. Creer un projet

```bash
php bin/neo make:project Blog
php bin/neo generate:default:config --project=Blog
```

### 3. Lancer le site

```bash
php -S localhost:8000 -t public
```

Puis ouvrir :

```text
http://localhost:8000
```

La valeur de `localhost:8000` doit correspondre a `access` dans `src/Blog/Config/app.config.php`.

### 4. Generer du code

```bash
php bin/neo make:controller PostController --project=Blog
php bin/neo make:service Mail --project=Blog
php bin/neo make:middleware AdminAccess --project=Blog
```

## Base de donnees, ORM, formulaires, auth et traductions

### Base de donnees

La connexion PDO est pilotee par `src/<Projet>/Config/database.config.php` via `DatabaseConnection`.

Exemple minimal :

```php
return [
    'enabled' => true,
    'use' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'dbname' => 'blog',
            'user' => 'root',
            'pass' => '',
            'charset' => 'utf8mb4',
        ],
    ],
];
```

### ORM et repositories

NeoPHP fournit une couche ORM avec modeles, repositories et relations par attributs.

Exemple de repository :

```php
$user = $this->userRepository->find(1);
$users = $this->userRepository->findAll()->getModels();
```

Exemple de modele :

```php
$user = new User();
$user->username = 'johndoe';
$user->lastname = 'Doe';
$user->save();
```

### Formulaires

Le moteur de formulaires gere :

- binding sur modele
- validation
- rendu Twig
- protection CSRF

Exemple Twig :

```twig
{{ form_start(form) }}
{{ form_row(form, 'username') }}
{{ form_row(form, 'email') }}
<button type="submit">Enregistrer</button>
{{ form_end(form) }}
```

### Auth

L'authentification est configuree dans `app.config.php` avec les cles `auth.*`.

Fonctions Twig utiles :

```twig
{% if auth_check() %}
    Bonjour {{ auth_user().username }}
{% endif %}
```

### Traductions

Les traductions sont stockees dans `src/<Projet>/Translations/<locale>/`.

Exemple :

```php
return [
    'welcome.title' => 'Bienvenue',
];
```

Utilisation Twig :

```twig
{{ trans('welcome.title') }}
```

## Gestion des erreurs

`ErrorHandler` centralise la gestion des erreurs et exceptions.

Comportement :

- log des erreurs framework
- dispatch d'un `ExceptionEvent`
- rendu de `errors/<code>.html.twig` si disponible
- fallback HTML sinon
- affichage detaille en `dev`
- message masque en `prod`

Exemple de vues personnalisees :

```text
src/Blog/App/Views/errors/404.html.twig
src/Blog/App/Views/errors/500.html.twig
```

## Tests PHPUnit

NeoPHP integre PHPUnit 11 par projet dans `src/<Projet>/Tests/`.

### Structure generee

Au premier `make:test`, NeoPHP genere automatiquement :

- `bootstrap.php`
- `phpunit.xml`
- `Config/database.config.test.php`
- les dossiers `Unit`, `Feature`, `Database`, `Middleware`

### Generer un test

```bash
php bin/neo make:test UserServiceTest --type=unit --project=Blog
php bin/neo make:test UserControllerTest --type=feature --project=Blog
php bin/neo make:test UserRepositoryTest --type=database --project=Blog
php bin/neo make:test AuthMiddlewareTest --type=middleware --project=Blog
```

### Lancer les tests

```bash
php bin/neo run:test UserServiceTest --project=Blog
php bin/neo run:test UserRepositoryTest --type=database --project=Blog
php bin/neo run:test:all --project=Blog
php bin/neo run:test:all --project=Blog --format=html
php bin/neo run:test:all --project=Blog --format=both --coverage
```

### Fonctionnement rapide

- `unit` : test d'une classe isolee
- `feature` : test HTTP de bout en bout
- `database` : test repository avec transaction + rollback automatique
- `middleware` : test de blocage ou passage d'un middleware

Rapports generes dans `src/<Projet>/Storage/reports/` :

- `junit.xml`
- `index.html`
- `coverage/`

Le mode `--coverage` necessite `Xdebug` ou `PCOV`.

## Dependances Composer

Dependances principales du framework :

- `twig/twig`
- `twig/intl-extra`
- `psr/container`
- `matthiasmullie/minify`
- `wikimedia/less.php`

Extensions PHP requises dans `composer.json` :

- `ext-pdo`
- `ext-zip`
- `ext-dom`
- `ext-libxml`
- `ext-ftp`
- `ext-curl`
- `ext-iconv`
- `ext-simplexml`

Pour les tests :

- `phpunit/phpunit` en `require-dev`

## Points a retenir

- `neo/` contient le coeur du framework
- `src/` contient les projets generes
- tout se pilote principalement via `php bin/neo`
- les routes reposent sur des attributs PHP
- chaque projet possede ses vues, assets, config, stockage et tests
- `make:test`, `run:test` et `run:test:all` couvrent le flux PHPUnit complet
- les tests `database` utilisent une config dediee et rollbackent automatiquement
