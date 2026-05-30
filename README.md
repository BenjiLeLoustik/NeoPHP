# NeoPHP

Framework PHP 8.1+ centre sur :

- un noyau applicatif dans `neo/`
- une CLI interne dans `bin/neo`
- des projets applicatifs isoles dans `src/<Projet>/`

Ce depot contient le moteur du framework et un projet d'exemple dans `src/Test/`.

## Sommaire

- [Vue d'ensemble](#vue-densemble)
- [Architecture du depot](#architecture-du-depot)
- [Cycle d'execution](#cycle-dexecution)
- [Structure d'un projet](#structure-dun-projet)
- [Conteneur DI et configuration](#conteneur-di-et-configuration)
- [Couche HTTP](#couche-http)
- [Routing et controleurs](#routing-et-controleurs)
- [Vues Twig, assets et traductions](#vues-twig-assets-et-traductions)
- [Base de donnees et QueryBuilder](#base-de-donnees-et-querybuilder)
- [ORM et repositories](#orm-et-repositories)
- [Formulaires, upload et validation](#formulaires-upload-et-validation)
- [Securite: auth, mot de passe, middlewares, csrf](#securite-auth-mot-de-passe-middlewares-csrf)
- [Events](#events)
- [Crons](#Crons)
- [Cache, logs et erreurs](#cache-logs-et-erreurs)
- [CLI et generateurs](#cli-et-generateurs)
- [Tests PHPUnit](#tests-phpunit)
- [Deploiement](#deploiement)
- [Dependances et prerequis](#dependances-et-prerequis)

## Vue d'ensemble

NeoPHP repose sur deux points d'entree :

- `public/index.php` pour le runtime HTTP
- `bin/neo` pour la CLI

Le coeur passe par `Neo\App`, qui :

- detecte le projet courant
- initialise le conteneur
- charge les configurations du projet
- enregistre les services coeur
- active Twig, la BDD, les assets, la traduction, l'auth et les events
- scanne les controleurs, routes et listeners
- execute la requete HTTP ou la commande CLI
- centralise la gestion des erreurs

## Architecture du depot

```text
.
|-- bin/
|   `-- neo
|-- neo/
|   |-- App.php
|   `-- Core/
|       |-- Asset/
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
|       |-- Translation/
|       |-- Utils/
|       |-- Validator/
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

Le projet d'exemple present dans le depot est `src/Test/`.

## Cartographie du coeur

Le noyau `neo/Core/` est structure par sous-systeme :

- `Asset/`
  gestion des assets, compilation CSS / JS / Less, manifest, helper Twig `asset()`
- `Console/`
  chargement automatique des commandes CLI et generateurs
- `Controller/`
  `AbstractController` et les raccourcis HTTP / auth / events / upload
- `Database/`
  connexion PDO, `QueryBuilder`, formulaires, pagination, ORM, repositories
- `DI/`
  conteneur de dependances et autowiring
- `Error/`
  `ErrorHandler` et `FrameworkException`
- `Event/`
  event dispatcher, attributs listeners, subscribers et evenements coeur
- `Http/`
  `Request`, responses, fichiers uploades, session, cookie, flash
- `Routing/`
  route collection, scan des controleurs, generation d'URL
- `Security/`
  auth session / token, JWT, middlewares, mot de passe, CSRF
- `Testing/`
  base de tests, scaffold PHPUnit, generation auto via `#[Test]`
- `Translation/`
  resolution de locale, chargement / ecriture des traductions, extension Twig
- `Utils/`
  config, cache, logs, helpers de chaine
- `Validator/`
  contraintes et moteur de validation
- `View/`
  integration Twig et enregistrement des fonctions / filtres

## Cycle d'execution

### En HTTP

`Neo\App` cherche un projet en lisant `src/*/Config/app.config.php` et compare la cle `access` a `HTTP_HOST` / `SERVER_NAME`.

Si un seul projet existe dans `src/`, il est selectionne automatiquement.

### En CLI

Le projet doit etre fourni explicitement avec `--project=NomDuProjet`.

Exemple :

```bash
php bin/neo cache:clear --project=Test
```

## Structure d'un projet

Un projet genere par `make:project` contient en pratique :

```text
src/Blog/
|-- App/
|   |-- Controllers/
|   |-- Event/
|   |-- Forms/
|   |-- Middlewares/
|   |-- Services/
|   `-- Views/
|-- Assets/
|   |-- css/
|   `-- js/
|-- Config/
|   |-- api.config.php
|   |-- app.config.php
|   |-- cache.config.php
|   |-- database.config.php
|   |-- deploy.config.php
|   |-- logger.config.php
|   |-- session.config.php
|   `-- twig.config.php
|-- Model/
|-- Repository/
|-- Storage/
|-- Tests/
`-- Translations/
```

Les configs sensibles `database.config.php`, `deploy.config.php` et `api.config.php` sont prevues pour etre ignorees par Git dans le `.gitignore` genere.

## Conteneur DI et configuration

Le conteneur `Neo\Core\DI\Container` fournit :

- `set()` pour enregistrer un service ou une factory
- `get()` pour resoudre un service
- `bind()` pour mapper une abstraction vers une implementation
- `make()` pour instancier une classe avec des parametres runtime
- autowiring via reflexion
- support des constructeurs de controleurs et de services

Exemple :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Services;

use Neo\Core\Utils\Cache\Cache;
use Neo\Core\Utils\Logger\Logger;

final class ReportService
{
    public function __construct(
        private Cache $cache,
        private Logger $logger
    ) {
    }

    public function build(): array
    {
        $this->logger->info('Generation du rapport');

        return $this->cache->get('report.latest', []);
    }
}
```

### Configuration

Le service `Config` charge tous les fichiers `*.config.php` du projet et peut merger les fichiers `*.config.test.php` pendant les tests.

Exemple :

```php
$appName = $this->getConfig()->from('app')->get('general.name');
$timezone = $this->getConfig()->from('app')->get('date.timezone', 'UTC');
$twigOptions = $this->getConfig()->from('twig')->all();
```

Exemple de `app.config.php` :

```php
<?php
declare(strict_types=1);

return [
    'general' => [
        'name' => 'Blog',
        'description' => 'Mon projet NeoPHP',
    ],
    'environment' => 'dev',
    'access' => 'localhost:8000',
    'date' => [
        'timezone' => 'Europe/Paris',
    ],
];
```

## Couche HTTP

La couche HTTP est composee principalement de :

- `Request`
- `Response`
- `JsonResponse`
- `RedirectResponse`
- `Session`
- `Cookie`
- `Flash`

### Request

`Request` expose notamment :

- `getMethod()`
- `getPath()`
- `query()`
- `body()`
- `header()`
- `file()`
- `getIp()`
- `getUserAgent()`
- `getPreviousUrl()`

Exemple :

```php
#[Route(path: '/search', name: 'search', methods: ['GET'])]
public function search(): Response
{
    $term = (string) $this->request->query('q', '');

    return $this->render('pages/search/index.html.twig', [
        'term' => $term,
        'ip' => $this->request->getIp(),
    ]);
}
```

### Response

`Response` sert a construire les reponses HTTP de base.

Exemple :

```php
$response = new Response();
$response->setStatusCode(200);
$response->setHeader('Content-Type', 'text/plain; charset=UTF-8');
$response->setContent('OK');
return $response;
```

Exemples de raccourcis via `AbstractController` :

```php
return $this->jsonSuccess(['saved' => true], 201);
return $this->jsonError('Not found', 404);
return $this->redirectToRoute('posts.index');
return $this->redirectToPath('/maintenance', 302);
```

### Session, cookie et flash

Le framework configure automatiquement la session depuis `session.config.php`.

Exemple dans un controleur :

```php
$this->getSession()->set('wizard.step', 2);
$step = $this->getSession()->get('wizard.step', 1);

$this->getCookie()->set('theme', 'dark');
$theme = $this->getCookie()->get('theme', 'light');

$this->getFlash()->add('success', 'Operation terminee');
```

Twig expose les messages flash via `flashes()` :

```twig
{{ flashes() }}
```

## Routing et controleurs

Le routing repose sur des attributs PHP scannes dans `src/<Projet>/App/Controllers`.

Fonctionnalites confirmees :

- prefix de route via `#[MainRoute(...)]`
- routes multi-methodes via `methods: [...]`
- parametres dynamiques `{id}`
- parametres optionnels `{slug?}`
- contraintes regex via `requirements`
- cache des routes hors environnement `dev`
- injection des arguments types via le conteneur

Exemple simple :

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

Exemple plus complet :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Controllers;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Http\Response\Response;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;
use Neo\Src\Blog\Repository\PostRepository;

#[MainRoute(path: '/posts', name: 'posts')]
final class PostController extends AbstractController
{
    public function __construct(private PostRepository $postRepository)
    {
    }

    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/posts/index.html.twig', [
            'posts' => $this->postRepository->findAll()->getModels(),
        ]);
    }

    #[Route(path: '/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        return $this->render('pages/posts/show.html.twig', [
            'post' => $this->postRepository->with('author')->find($id),
        ]);
    }
}
```

Helpers exposes par `AbstractController` :

- `render()`
- `template()`
- `redirectToRoute()`
- `redirectToPath()`
- `redirectBack()`
- `json()`
- `jsonSuccess()`
- `jsonError()`
- `auth()`
- `dispatch()`
- `upload()`
- acces a `Session`, `Cookie`, `Flash`, `Logger`, `Cache`, `Config`

Twig expose aussi :

- `path()`
- `currentRoute()`

## Vues Twig, assets et traductions

### Vues Twig

Les vues sont chargees depuis `src/<Projet>/App/Views`.

Twig est initialise avec :

- cache optionnel
- debug optionnel
- `twig/intl-extra`
- global `app`
- fonctions ajoutees par le framework

Exemple :

```twig
{% extends 'layouts/base_layout.html.twig' %}

{% block title %}Liste des posts{% endblock %}

{% block content %}
    <h1>Posts</h1>

    <ul>
        {% for post in posts %}
            <li>
                <a href="{{ path('posts.show', {id: post.id}) }}">
                    {{ post.title }}
                </a>
            </li>
        {% endfor %}
    </ul>
{% endblock %}
```

### Assets

Les assets sources vivent dans `src/<Projet>/Assets/`.

Le composant `AssetHandler` :

- expose `asset()`
- compile `css`, `js` et `less`
- minifie CSS et JS
- genere des noms avec hash
- ecrit `public/builds/<Projet>/manifest.json`
- sert les fichiers compiles depuis `public/builds/<Projet>/assets/`

Exemple Twig :

```twig
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<script src="{{ asset('js/app.js') }}"></script>
```

Arborescence source :

```text
src/Blog/Assets/
|-- css/
|   `-- app.css
`-- js/
    `-- app.js
```

### Traductions

Les traductions sont chargees depuis `src/<Projet>/Translations/<locale>/`.

Fonctions Twig disponibles :

- `translate()`
- `trans()`
- `getLocales()`
- `getLocale()`
- `isEnabled_translation()`

Comportement notable :

- la locale est resolue depuis la config et les cookies
- `setLocale()` persiste la langue dans un cookie `lang`
- en environnement `dev`, une cle manquante peut etre auto-enregistree

Exemple de fichier `src/Blog/Translations/fr/messages.php` :

```php
<?php

return [
    'page' => [
        'title' => 'Bienvenue sur le blog',
    ],
    'button' => [
        'save' => 'Enregistrer',
    ],
];
```

Exemple Twig :

```twig
<h1>{{ trans('messages.page.title') }}</h1>
<button>{{ trans('messages.button.save') }}</button>
```

Exemple dans un controleur :

```php
#[Route(path: '/change-locale/{locale}', name: 'change.locale', methods: ['GET'])]
public function changeLocale(string $locale, TranslationManager $translator): Response
{
    $translator->setLocale($locale);
    return $this->redirectBack('home.index');
}
```

### Helper de chaine

Le service `StringExtension` expose `slugify()` en PHP, en fonction Twig et en filtre Twig.

Exemple :

```php
$slug = $this->slugify('Mon Titre Exemple');
```

```twig
{{ 'Mon Titre Exemple'|slugify }}
{{ slugify('Mon Titre Exemple') }}
```

## Base de donnees et QueryBuilder

La connexion PDO est pilotee par `Config/database.config.php` via `DatabaseConnection`.

Exemple minimal :

```php
return [
    'enabled' => true,
    'use' => 'default',
    'connections' => [
        'default' => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'blog',
            'user' => 'root',
            'pass' => '',
            'charset' => 'utf8mb4',
        ],
    ],
];
```

### QueryBuilder

Le `QueryBuilder` couvre notamment :

- `table()`
- `select()`
- `where()`, `orWhere()`
- `whereLike()`, `whereIn()`, `whereNull()`, `whereNotNull()`
- `between()`
- `join()`, `leftJoin()`
- `orderBy()`, `groupBy()`
- `limit()`, `offset()`
- `get()`, `first()`, `count()`
- `insert()`, `insertGetId()`, `update()`, `delete()`
- `paginate()`
- transactions via `transaction()`

Exemple :

```php
<?php
declare(strict_types=1);

use Neo\Core\Database\Builder\QueryBuilder;

$qb = (new QueryBuilder())
    ->table('posts')
    ->select(['posts.id', 'posts.title'])
    ->where('posts.user_id', '=', 1)
    ->whereLike('posts.title', 'neo')
    ->orderBy('posts.id', 'DESC')
    ->limit(10);

$rows = $qb->get();
```

Exemple avec transaction :

```php
(new QueryBuilder())
    ->table('posts')
    ->transaction(function (QueryBuilder $qb): void {
        $qb->table('posts')->insert([
            'user_id' => 1,
            'title' => 'Post transactionnel',
            'content' => 'Contenu',
        ]);
    });
```

## ORM et repositories

### ORM

`AbstractModel` couvre notamment :

- table et cle primaire configurables
- hydratation typee via reflexion
- `save()`
- `fill()`
- `toArray()`
- `toDatabase()`
- identity map
- chargement lazy et eager des relations
- support du soft delete si colonne `deleted_at`

Relations disponibles :

- `#[HasOne(...)]`
- `#[HasMany(...)]`
- `#[BelongsTo(...)]`
- `#[BelongsToMany(...)]`

Exemple de modeles :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\Model;

use Neo\Core\Database\ORM\Attribute\BelongsTo;
use Neo\Core\Database\ORM\Attribute\HasMany;
use Neo\Core\Database\ORM\Model\AbstractModel;

final class User extends AbstractModel
{
    protected static ?string $table = 'users';

    public ?int $id = null;
    public string $firstname;
    public string $email;

    #[HasMany(target: Post::class, foreignKey: 'user_id', localKey: 'id')]
    public array $posts = [];
}

final class Post extends AbstractModel
{
    protected static ?string $table = 'posts';

    public ?int $id = null;
    public int $user_id;
    public string $title;
    public string $content;

    #[BelongsTo(target: User::class, foreignKey: 'user_id', ownerKey: 'id')]
    public ?User $author = null;
}
```

Exemple d'utilisation :

```php
$post = new Post();
$post->user_id = 1;
$post->title = 'Premier post';
$post->content = 'Contenu';
$post->save();
```

### Repositories

`AbstractRepository` fournit :

- `find()`
- `findAll()`
- `findBy()`
- `create()`
- `update()`
- `delete()`
- `restore()`
- `forceDelete()`
- `with()`
- `withTrashed()`
- `onlyTrashed()`
- `paginate()`
- acces au `QueryBuilder`

Exemple :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\Repository;

use Neo\Core\Database\ORM\Repository\AbstractRepository;
use Neo\Src\Blog\Model\Post;

final class PostRepository extends AbstractRepository
{
    protected string $modelClass = Post::class;
}
```

Exemple d'utilisation :

```php
$posts = $postRepository
    ->with('author')
    ->findAll()
    ->getModels();

$post = $postRepository->find(10);
```

## Formulaires, upload et validation

### Formulaires

NeoPHP embarque :

- `FormBuilder`
- `Form`
- plusieurs types de champs
- rendu Twig
- CSRF
- validation

Helpers Twig disponibles :

- `form_start()`
- `form_end()`
- `form_row()`
- `form_widget()`
- `form_label()`
- `form_error()`
- `form_errors()`
- `form_csrf()`
- helpers pour les collections

Exemple de classe de formulaire :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Forms;

use Neo\Core\Database\Builder\FormBuilder;
use Neo\Core\Database\Form\Form;
use Neo\Core\Database\Form\Type\EmailType;
use Neo\Core\Database\Form\Type\SubmitType;
use Neo\Core\Database\Form\Type\TextType;
use Neo\Core\DI\Container;
use Neo\Core\Http\Request;
use Neo\Src\Blog\Model\User;

final class UserForm
{
    private Request $request;

    public function __construct(Container $container)
    {
        $this->request = $container->get(Request::class);
    }

    public function build(?User $user = null): Form
    {
        $user ??= new User();

        $form = (new FormBuilder($user))
            ->add('firstname', TextType::class, ['label' => 'Prenom'])
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('submit', SubmitType::class, ['label' => 'Enregistrer'])
            ->generate();

        $form->addCsrfField();
        $form->handleRequest($this->request);
        $form->setData($user);
        $form->populateData();

        return $form;
    }
}
```

Exemple Twig :

```twig
{{ form_start(form) }}
{{ form_row(form, 'firstname') }}
{{ form_row(form, 'email') }}
{{ form_end(form) }}
```

### Upload dans un controleur

Le point d'entree applicatif est `AbstractController::upload()`.

Signature :

```php
$filename = $this->upload(
    string $field,
    string $name,
    array $extensions,
    string $directory
);
```

Ce helper :

- recupere le fichier via `Request::file()`
- verifie l'upload PHP
- lit l'extension d'origine
- refuse `php`, `phtml`, `exe`, `sh`, `js`
- verifie la whitelist fournie
- cree le dossier cible dans `src/<Projet>/Assets/<directory>`
- deplace le fichier
- renvoie le nom final du fichier

Exemple :

```php
#[Route(path: '/profile/avatar', name: 'avatar.upload', methods: ['POST'])]
public function uploadAvatar(): Response
{
    $filename = $this->upload(
        field: 'avatar',
        name: 'user_' . (string) $this->auth()->user()?->id,
        extensions: ['jpg', 'jpeg', 'png', 'webp'],
        directory: 'uploads/avatars'
    );

    return $this->jsonSuccess([
        'filename' => $filename,
        'path' => 'uploads/avatars/' . $filename,
    ]);
}
```

Affichage ensuite :

```twig
<img src="{{ asset('uploads/avatars/' ~ user.avatar) }}" alt="Avatar">
```

### Validation

Le validateur repose sur des attributs de contraintes poses sur les proprietes des modeles.

Contraintes presentes dans le framework :

- `NotBlank`
- `Length`
- `Email`
- `Date`
- `Choice`
- `Range`
- `Regex`
- `Url`
- `Unique`
- `EqualToField`

Exemple :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\Model;

use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\Validator\Assert\Email;
use Neo\Core\Validator\Assert\EqualToField;
use Neo\Core\Validator\Assert\Length;
use Neo\Core\Validator\Assert\NotBlank;

final class RegisterUser extends AbstractModel
{
    #[NotBlank(message: 'Le prenom est obligatoire.')]
    public string $firstname = '';

    #[NotBlank(message: 'L email est obligatoire.')]
    #[Email(message: 'L email est invalide.')]
    public string $email = '';

    #[Length(min: 8, message: 'Le mot de passe doit faire au moins 8 caracteres.')]
    public string $password = '';

    #[EqualToField(field: 'password', message: 'Les mots de passe doivent etre identiques.')]
    public string $password_confirm = '';
}
```

## Securite: auth, mot de passe, middlewares, csrf

### Authentification

L'auth est pilotee depuis `app.config.php`.

Le framework supporte deux guards :

- `session`
- `token`

Le guard `token` s'appuie sur `JwtManager`.

Configuration type :

```php
'auth' => [
    'enabled' => true,
    'model' => User::class,
    'identifier' => 'email',
    'password' => 'password',
    'guard' => 'session',
    'role' => [
        'model' => Role::class,
        'foreign_key' => 'role_id',
        'field' => 'slug',
    ],
    'options' => [
        'secret' => 'change-me',
        'expiration' => 3600,
        'algorithm' => 'HS256',
    ],
],
```

API de `AuthManager` :

- `attempt()`
- `login()`
- `logout()`
- `check()`
- `user()`
- `hasRole()`
- `generateToken()`

Exemple de login session :

```php
#[MainRoute(path: '/login', name: 'login')]
final class LoginController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET', 'POST'])]
    public function index(): Response
    {
        if ($this->request->getMethod() === 'GET') {
            return $this->render('pages/auth/login.html.twig');
        }

        $ok = $this->auth()->attempt([
            'email' => (string) $this->request->body('email'),
            'password' => (string) $this->request->body('password'),
        ]);

        if (!$ok) {
            return $this->jsonError('Identifiants invalides', 401);
        }

        return $this->redirectToRoute('dashboard.index');
    }
}
```

Exemple de login token :

```php
#[MainRoute(path: '/api', name: 'api')]
final class ApiAuthController extends AbstractController
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    #[Route(path: '/login', name: 'login', methods: ['POST'])]
    public function login(): Response
    {
        $email = (string) $this->request->body('email');
        $password = (string) $this->request->body('password');

        $ok = $this->auth()->attempt([
            'email' => $email,
            'password' => $password,
        ]);

        if (!$ok) {
            return $this->jsonError('Identifiants invalides', 401);
        }

        $user = $this->userRepository->findBy('email', $email);

        if ($user === null) {
            return $this->jsonError('Utilisateur introuvable', 401);
        }

        return $this->jsonSuccess([
            'token' => $this->auth()->generateToken($user),
        ]);
    }
}
```

Twig expose :

- `auth_check()`
- `auth_user()`
- `auth_has_role()`
- `csrf_token()`

### PasswordManager

Le service `PasswordManager` fournit :

- `hash()`
- `verify()`
- `needsRehash()`
- `generate()`
- `getInfo()`

Exemple :

```php
$hash = $this->getPasswordManager()->hash('secret123');
$ok = $this->getPasswordManager()->verify('secret123', $hash);
```

### Middlewares

Attributs supportes :

- `#[Middleware(...)]`
- `#[RateLimit(...)]`
- `#[Maintenance(...)]`

Middlewares coeur :

- `AuthMiddleware`
- `GuestMiddleware`
- `RoleMiddleware`
- `RateLimitMiddleware`
- `ExampleMiddleware`

Exemple de middleware applicatif :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Middlewares;

use Neo\Core\DI\Container;
use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;

final class AdminAccessMiddleware implements MiddlewareInterface
{
    private AuthManager $auth;

    public function __construct(Container $container)
    {
        $this->auth = $container->get(AuthManager::class);
    }

    public function handle(): bool
    {
        return $this->auth->check() && $this->auth->hasRole('admin');
    }
}
```

Exemple d'utilisation :

```php
#[MainRoute(path: '/admin', name: 'admin')]
#[Middleware(use: AuthMiddleware::class, redirect: 'login.index')]
#[Middleware(use: RoleMiddleware::class, params: ['role' => 'admin'])]
final class DashboardController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    #[RateLimit(maxAttempts: 20, decaySeconds: 60)]
    public function index(): Response
    {
        return $this->render('pages/admin/index.html.twig');
    }
}
```

### CSRF

Le manager CSRF stocke les tokens en session sous `_csrf_tokens`.

Comportement :

- generation via `generateToken()`
- expiration par defaut a 3600 secondes
- validation via `validateToken()`
- integration dans les formulaires via `form_csrf()` et `csrf_token()`

## Events

NeoPHP embarque un event dispatcher et plusieurs evenements coeur :

- `RequestEvent`
- `ResponseEvent`
- `ExceptionEvent`

Les listeners applicatifs sont attendus dans `src/<Projet>/App/Event/Listener`.

Ils peuvent etre declares :

- via `#[AsListener(event: ..., priority: ...)]`
- via `EventSubscriberInterface`

Exemple complet :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Event;

use Neo\Core\Event\AbstractEvent;

final class UserRegisteredEvent extends AbstractEvent
{
    public function __construct(public readonly int $userId)
    {
    }
}
```

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Event\Listener;

use Neo\Core\Event\Attribute\AsListener;
use Neo\Src\Blog\App\Event\UserRegisteredEvent;

#[AsListener(event: UserRegisteredEvent::class, priority: 0)]
final class SendWelcomeEmailListener
{
    public function handle(UserRegisteredEvent $event): void
    {
        $userId = $event->userId;
    }
}
```

Exemple dans un controleur :

```php
#[Route(path: '/register', name: 'register', methods: ['POST'])]
public function register(): Response
{
    $user = new \Neo\Src\Blog\Model\User();
    $user->firstname = (string) $this->request->body('firstname');
    $user->email = (string) $this->request->body('email');
    $user->password = $this->getPasswordManager()->hash(
        (string) $this->request->body('password')
    );
    $user->save();

    $this->dispatch(new \Neo\Src\Blog\App\Event\UserRegisteredEvent((int) $user->id));

    return $this->jsonSuccess([
        'id' => $user->id,
    ], 201);
}
```

## Crons

NeoPHP embarque un systeme de taches planifiees executables via la CLI.

Les crons applicatifs sont attendus dans le projet courant et peuvent etre lances manuellement ou automatiquement via le systeme d'exploitation.

### Creer un cron

Pour generer un nouveau cron :

```bash
php bin/neo make:cron <NomDuCron> --project=Blog
```

Exemple :

```bash
php bin/neo make:cron CleanupTempFiles --project=Blog
```

Le generateur cree automatiquement le fichier du cron dans le projet cible.

### Lister les crons

Pour afficher tous les crons disponibles d'un projet :

```bash
php bin/neo cron:list --project=Blog
```

Cette commande affiche notamment :

- le nom du cron
- sa description
- sa frequence
- son statut

### Executer les crons

Pour executer tous les crons du projet :

```bash
php bin/neo cron:run --project=Blog
```

Cette commande est celle qui doit etre planifiee automatiquement par le systeme d'exploitation.

### Execution automatique des crons

#### Linux

Sous Linux, les crons sont generalement pilotes via `crontab`.

Ouvrir la configuration cron :

```bash
crontab -e
```

Executer les crons NeoPHP toutes les minutes :

```bash
* * * * * php /path/to/project/bin/neo cron:run --project=Blog
```

Exemple concret :

```bash
* * * * * php /var/www/neophp/bin/neo cron:run --project=Blog
```

Verifier les logs cron :

```bash
grep CRON /var/log/syslog
```

#### macOS

macOS supporte egalement `crontab`.

Ouvrir la configuration :

```bash
crontab -e
```

Ajouter :

```bash
* * * * * php /path/to/project/bin/neo cron:run --project=Blog
```

Exemple :

```bash
* * * * * php /Users/benjamin/Sites/neophp/bin/neo cron:run --project=Blog
```

Verifier les taches :

```bash
crontab -l
```

#### Windows

Sous Windows, utiliser le Planificateur de taches.

Commande a executer :

```bash
php C:\path\to\project\bin\neo cron:run --project=Blog
```

Exemple :

```bash
php C:\Sites\NeoPHP\bin\neo cron:run --project=Blog
```

Configuration conseillee :

- declencheur : toutes les minutes
- programme : `php.exe`
- arguments :

```bash
C:\Sites\NeoPHP\bin\neo cron:run --project=Blog
```

Le Planificateur de taches peut etre ouvert avec :

```text
Win + R -> taskschd.msc
```

#### Docker

Exemple avec une boucle simple :

```bash
while true; do
    php bin/neo cron:run --project=Blog
    sleep 60
done
```

Exemple via `docker-compose` :

```yaml
services:
  cron:
    command: sh -c "while true; do php bin/neo cron:run --project=Blog; sleep 60; done"
```

### Conseils

En production, il est recommande :

- d'executer `cron:run` toutes les minutes
- de journaliser les erreurs via le `Logger`
- d'eviter les traitements bloquants trop longs
- d'utiliser des files d'attente pour les traitements lourds
- de surveiller les executions via les logs applicatifs ou systeme

## Cache, logs et erreurs

### Cache

Le service `Cache` repose sur des fichiers dans `src/<Projet>/Storage/cache`.

API :

- `set()`
- `get()`
- `delete()`
- `clear()`

Exemple :

```php
$this->getCache()->set('homepage.posts', $posts, 600);
$posts = $this->getCache()->get('homepage.posts', []);
```

### Logger

Le service `Logger` lit `logger.config.php` et gere :

- niveaux de logs
- channels
- rotation
- archivage zip

Niveaux supportes :

- `debug`
- `info`
- `notice`
- `warning`
- `error`
- `critical`
- `alert`
- `emergency`

Exemple :

```php
$this->getLogger()->channel('framework')->error(
    'Erreur metier',
    ['post_id' => 12],
    'PostController::show'
);
```

### Gestion des erreurs

`ErrorHandler` :

- intercepte exceptions et erreurs PHP
- loggue les erreurs
- dispatch un `ExceptionEvent`
- rend `errors/<code>.html.twig` si present
- fournit un fallback HTML sinon
- affiche plus de details en `dev`

Exemple de vues d'erreur :

```text
src/Blog/App/Views/errors/404.html.twig
src/Blog/App/Views/errors/500.html.twig
```

Exemple `404.html.twig` :

```twig
{% extends 'layouts/base_layout.html.twig' %}

{% block content %}
    <h1>404</h1>
    <p>{{ message }}</p>
{% endblock %}
```

## CLI et generateurs

Afficher l'aide globale :

```bash
php bin/neo
```

Afficher l'aide d'une commande :

```bash
php bin/neo <commande> --help
```

Commandes disponibles :

- `make:project`
- `delete:project`
- `sync:projects`
- `generate:default:config`
- `make:config`
- `composer:require`
- `make:controller`
- `make:service`
- `make:middleware`
- `make:event`
- `make:event:listener`
- `make:crud`
- `cache:clear`
- `asset:reload`
- `make:deployment`
- `make:test`
- `make:test:auto`
- `run:test`
- `run:test:all`

### Generateurs principaux

Exemples :

```bash
php bin/neo make:project Blog
php bin/neo make:controller PostController --project=Blog
php bin/neo make:controller ApiPostController --api --project=Blog
php bin/neo make:service Mail --project=Blog
php bin/neo make:middleware AdminAccess --project=Blog
php bin/neo make:event UserRegistered --project=Blog
php bin/neo make:event:listener SendWelcomeEmail --event=UserRegistered --project=Blog
php bin/neo make:crud Post --project=Blog
php bin/neo make:config mail --project=Blog
```

Exemple de commande interactive de config :

```bash
php bin/neo make:config mail --project=Blog
```

Tu peux ensuite saisir par exemple :

- `smtp.host`
- `smtp.port`
- `smtp.user`
- `smtp.pass`

Le generateur ecrira un tableau PHP imbrique.

### Maintenance de projet

Exemples :

```bash
php bin/neo generate:default:config --project=Blog
php bin/neo composer:require league/flysystem --project=Blog
php bin/neo sync:projects
php bin/neo cache:clear --project=Blog
php bin/neo asset:reload --project=Blog
```

## Tests PHPUnit

Le framework embarque une couche de test par projet avec PHPUnit 11.

Commandes disponibles :

- `make:test`
- `make:test:auto`
- `run:test`
- `run:test:all`

Au premier `make:test` ou `make:test:auto`, NeoPHP genere :

- `src/<Projet>/Tests/bootstrap.php`
- `src/<Projet>/Tests/phpunit.xml`
- `src/<Projet>/Tests/Config/database.config.test.php`
- les dossiers `Unit`, `Feature`, `Database`, `Middleware`

Classes de base :

- `TestCase`
- `FeatureTestCase`
- `DatabaseTestCase`
- `MiddlewareTestCase`

Fonctionnalites confirmees :

- simulation de requetes HTTP pour les tests feature
- transactions et rollback automatique pour les tests database
- surcharge de config via `*.config.test.php`
- synchronisation du schema dev vers la base de test
- rapports `junit.xml` et couverture HTML

### Tests manuels

Exemples :

```bash
php bin/neo make:test UserServiceTest --type=unit --project=Blog
php bin/neo make:test UserControllerTest --type=feature --project=Blog
php bin/neo make:test UserRepositoryTest --type=database --project=Blog
php bin/neo make:test AuthMiddlewareTest --type=middleware --project=Blog
```

### Generation automatique avec `#[Test]`

Le systeme automatique repose sur l'attribut `Neo\Core\Testing\Attribute\Test`.

Il peut etre pose :

- sur une classe
- sur une methode publique

Signature actuelle :

```php
#[Test(
    type: 'auto',
    cases: [],
    route: null,
    httpMethod: 'GET',
    dataset: [],
    skip: false,
    extends: null
)]
```

Ce que fait `make:test:auto` :

- prepare le scaffold PHPUnit si besoin
- scanne tous les fichiers PHP du projet
- charge les classes qui contiennent `#[Test]`
- lit l'attribut au niveau classe et methode
- deduit un type de test
- choisit un template
- genere le fichier dans `Tests/<Type>/`

Inference du type si `type = auto` :

- `Repository` => `database`
- `Controller` => `feature`
- `Middleware` => `middleware`
- sinon => `unit`

Exemple sur une classe de service :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Services;

use Neo\Core\Testing\Attribute\Test;

#[Test(type: 'unit', cases: ['it_works', 'returns_slug'])]
final class SlugService
{
    public function slugify(string $value): string
    {
        return strtolower(trim(str_replace(' ', '-', $value)));
    }
}
```

Exemple sur un repository :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\Repository;

use Neo\Core\Database\ORM\Repository\AbstractRepository;
use Neo\Core\Testing\Attribute\Test;
use Neo\Src\Blog\Model\User;

#[Test(
    type: 'database',
    cases: ['find_by_email', 'save'],
    dataset: [
        'table' => 'users',
        'data' => [
            'firstname' => 'John',
            'email' => 'john@example.com',
        ],
    ],
)]
final class UserRepository extends AbstractRepository
{
    protected string $modelClass = User::class;
}
```

Exemple sur une methode de controleur :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Controllers;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Http\Response\Response;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;
use Neo\Core\Testing\Attribute\Test;

#[MainRoute(path: '/login', name: 'login')]
final class AuthController extends AbstractController
{
    #[Test(
        route: '/login',
        httpMethod: 'POST',
        cases: ['returns_success', 'rejects_invalid_credentials']
    )]
    public function login(): Response
    {
        return $this->jsonSuccess();
    }
}
```

Options utiles :

```bash
php bin/neo make:test:auto --project=Blog
php bin/neo make:test:auto --project=Blog --only=database
php bin/neo make:test:auto --project=Blog --dry-run
php bin/neo make:test:auto --project=Blog --force
php bin/neo run:test:all --project=Blog --coverage
```

## Deploiement

La commande `make:deployment` prepare un deploiement FTP a partir de `src/<Projet>/Config/deploy.config.php`.

Le flux implemente :

- patch temporaire de `app.config.php` en `prod`
- patch temporaire de `public/index.php`
- fusion du `composer.json` racine et du `composer.json` projet
- installation des dependances en `--no-dev`
- compression de `vendor/`
- upload FTP du framework, du projet et du dossier public
- upload de `vendor.zip`
- execution d'un script temporaire de dezippage cote serveur

Cles attendues dans `deploy.config.php` :

- `ftp.host`
- `ftp.user`
- `ftp.pass`
- `remote.domain`
- `remote.framework_dir`
- `remote.public_dir`

Exemple :

```php
<?php
declare(strict_types=1);

return [
    'ftp' => [
        'host' => 'ftp.example.com',
        'user' => 'my-user',
        'pass' => 'my-pass',
    ],
    'remote' => [
        'domain' => 'example.com',
        'framework_dir' => 'domains/example.com/neo',
        'public_dir' => 'domains/example.com/public_html',
    ],
];
```

## Dependances et prerequis

### PHP

- PHP `>= 8.1`

### Extensions PHP requises

- `ext-pdo`
- `ext-zip`
- `ext-libxml`
- `ext-dom`
- `ext-ftp`
- `ext-iconv`
- `ext-curl`
- `ext-simplexml`

### Dependances principales

- `twig/twig`
- `twig/intl-extra`
- `psr/container`
- `matthiasmullie/minify`
- `wikimedia/less.php`

### Dependances de developpement

- `phpunit/phpunit`
- `phpstan/phpstan`

## Resume

NeoPHP couvre aujourd'hui :

- noyau applicatif multi-projets
- conteneur DI avec autowiring
- configuration par fichiers PHP
- couche HTTP, responses, sessions, cookies et flash
- routing par attributs
- controleurs et vues Twig
- pipeline d'assets CSS, JS et Less
- traduction
- QueryBuilder, ORM et repositories
- formulaires, validation, upload et CSRF
- auth session / token, mot de passe et middlewares
- events
- cache, logs et gestion des erreurs
- CLI de generation et d'administration
- testing manuel et generation automatique via `#[Test]`
- deploiement FTP integre

Le point cle du depot reste le meme :

- `neo/` contient le moteur
- `src/` contient les applications
- `php bin/neo ...` pilote l'essentiel du workflow