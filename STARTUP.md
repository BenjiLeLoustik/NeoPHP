# Start Up

Ce guide vous permet de créer votre première application NeoPHP de A à Z. Il suppose une connaissance basique de PHP et de la ligne de commande.

---

## Sommaire

1. [Prérequis](#1-prérequis)
2. [Installation du framework](#2-installation-du-framework)
3. [Créer un nouveau projet](#3-créer-un-nouveau-projet)
4. [Lancer le serveur de développement](#4-lancer-le-serveur-de-développement)
5. [Fichiers de configuration](#5-fichiers-de-configuration)
6. [Routes et contrôleurs](#6-routes-et-contrôleurs)
7. [Vues avec Twig](#7-vues-avec-twig)
8. [Base de données et ORM](#8-base-de-données-et-orm)
9. [Formulaires](#9-formulaires)
10. [Authentification](#10-authentification)
11. [Middlewares](#11-middlewares)
12. [Référence des commandes CLI](#12-référence-des-commandes-cli)

---

## 1. Prérequis

| Outil | Version minimale |
|-------|-----------------|
| PHP | 8.5 |
| Composer | 2.x |
| Git | — |
| MySQL / MariaDB | — (optionnel) |

Extensions PHP requises : `pdo`, `zip`, `curl`, `fileinfo`, `dom`, `iconv`.

Vérification rapide :

```bash
php -v
php -m | grep -E "pdo|zip|curl|fileinfo"
composer -V
```

---

## 2. Installation du framework

Clonez le dépôt NeoPHP, puis installez les dépendances PHP via Composer :

```bash
# HTTPS
git clone https://github.com/NeoPHP-Dev/NeoPHP.git

# SSH
git clone git@github.com:NeoPHP-Dev/NeoPHP.git

# GitHub CLI
gh repo clone NeoPHP-Dev/NeoPHP

cd NeoPHP
composer install
```

---

## 3. Créer un nouveau projet

NeoPHP peut héberger un ou plusieurs projets indépendants dans le dossier `src/`. La commande suivante génère automatiquement toute l'arborescence nécessaire pour un nouveau site :

```bash
php bin/neo project:create MonSite
```

### Structure générée

Chaque projet est autonome et regroupe son code, ses templates, sa configuration et ses assets :

```
src/MonSite/
├── App/
│   ├── Controllers/        vos contrôleurs
│   ├── Middlewares/        vos middlewares
│   └── Services/           vos services métier
├── Assets/
│   ├── css/
│   └── js/
├── Config/
│   ├── app.config.php
│   ├── auth.config.php
│   ├── cache.config.php
│   ├── database.config.php
│   ├── logger.config.php
│   ├── session.config.php
│   └── twig.config.php
├── Database/
│   ├── Entity/             vos entités (modèles)
│   ├── Migrations/         fichiers de migration SQL
│   └── Repository/         accès aux données
├── Storage/                logs, cache, sessions
├── Templates/              vos templates Twig
└── Translations/           fichiers de traduction
```

---

## 4. Lancer le serveur de développement

NeoPHP embarque le serveur PHP intégré, pratique pour développer sans configurer Apache ou Nginx en local :

```bash
php bin/neo app:serve MonSite
```

Le site est accessible sur **http://localhost:8000**.

> Le point d'entrée HTTP est `public/index.php`. En production, configurez Apache ou Nginx pour pointer la racine web sur ce dossier `public/`.

---

## 5. Fichiers de configuration

Chaque projet possède son propre dossier `Config/`, avec un fichier dédié par domaine (application, base de données, moteur de templates, etc.). Voici les trois fichiers à connaître pour démarrer.

### app.config.php

Fichier de configuration principal du projet. La clé `access` détermine quel projet est servi selon le domaine HTTP appelé, ce qui permet d'héberger plusieurs sites côte à côte.

```php
// src/MonSite/Config/app.config.php
return [
    'general' => [
        'name'    => 'MonSite',
        'version' => '1.0.0',
    ],
    'environment' => 'dev',         // 'dev' ou 'prod'
    'access'      => 'localhost:8000', // doit correspondre à l'URL d'accès
    'date' => [
        'timezone' => 'Europe/Paris',
    ],
];
```

La section `general` est disponible dans tous les templates via la variable globale `app` :

```twig
<title>{{ app.name }}</title>
```

### database.config.php

Définit la ou les connexions disponibles pour le projet. Plusieurs connexions peuvent être déclarées sous `connections`, la clé `use` indiquant celle utilisée par défaut.

```php
// src/MonSite/Config/database.config.php
return [
    'enabled' => true,
    'use'     => 'default',
    'connections' => [
        'default' => [
            'driver'  => 'mysql',
            'host'    => 'localhost',
            'port'    => 3306,
            'dbname'  => 'monsite',
            'user'    => 'root',
            'pass'    => '',
            'charset' => 'utf8mb4',
        ],
    ],
];
```

### twig.config.php

Réglages du rendu Twig. Pensez à activer `cache` et désactiver `debug` avant une mise en production.

```php
// src/MonSite/Config/twig.config.php
return [
    'cache'            => false,  // true en production
    'debug'            => true,
    'auto_reload'      => true,
    'auto_escape'      => 'html',
    'charset'          => 'UTF-8',
    'strict_variables' => false,
];
```

---

## 6. Routes et contrôleurs

### Générer un contrôleur

```bash
php bin/neo make:controller TaskController --project=MonSite
```

Cela crée `src/MonSite/App/Controllers/TaskController.php`.

### Déclarer des routes par attributs

Les routes sont déclarées par attributs PHP directement sur les classes et méthodes, sans fichier de routage séparé. `#[MainRoute]` définit un préfixe de chemin et de nom pour tout le contrôleur ; `#[Route]` déclare chaque route individuelle.

```php
<?php
declare(strict_types=1);

namespace Neo\Src\MonSite\App\Controllers;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Http\Response\Types\Response;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;
use Neo\Src\MonSite\Database\Repository\TaskRepository;

#[MainRoute(path: '/tasks', name: 'tasks')]
final class TaskController extends AbstractController
{
    public function __construct(
        private TaskRepository $taskRepository
    ) {}

    // GET /tasks/  → nom : tasks.index
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/tasks/index.html.twig', [
            'tasks' => $this->taskRepository->findAll(),
        ]);
    }

    // GET /tasks/new  → nom : tasks.new
    #[Route(path: '/new', name: 'new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->render('pages/tasks/form.html.twig');
    }

    // POST /tasks/new  → nom : tasks.create
    #[Route(path: '/new', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        // persistance via FormFactory — voir section Formulaires
        $this->getFlash()->add('success', 'Tâche créée.');
        return $this->redirect('/tasks');
    }

    // GET /tasks/{id}  → paramètre dynamique
    #[Route(path: '/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $task = $this->taskRepository->find($id);
        return $this->render('pages/tasks/show.html.twig', ['task' => $task]);
    }

    // POST /tasks/{id}/delete
    #[Route(path: '/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id): Response
    {
        // suppression — voir section Base de données
        $this->getFlash()->add('success', 'Tâche supprimée.');
        return $this->redirect('/tasks');
    }
}
```

### Lire les données de la requête

```php
// Paramètres GET
$page = $this->getRequest()->query('page', 1);

// Corps POST ou JSON
$title = $this->getRequest()->body('title');

// En-têtes
$token = $this->getRequest()->header('Authorization');
```

### Construire une réponse

Un contrôleur peut retourner une vue Twig, une redirection ou une réponse JSON :

```php
// Rendu d'un template Twig
return $this->render('pages/index.html.twig', ['data' => $data]);

// Redirection
return $this->redirect('/tasks');

// JSON
return $this->json(['status' => 'ok']);
return $this->jsonSuccess(['id' => 42]);
return $this->jsonError('Non trouvé', 404);
```

### Vérifier les routes enregistrées

Pratique pour vérifier qu'une route est bien enregistrée et connaître son nom exact :

```bash
php bin/neo debug:router --project=MonSite
```

---

## 7. Vues avec Twig

### Emplacement des templates

Les templates se trouvent dans `src/<Projet>/Templates/`. Le chemin passé à `render()` est **relatif à ce dossier**.

```
src/MonSite/Templates/
├── layouts/
│   └── base.html.twig
├── pages/
│   └── tasks/
│       ├── index.html.twig
│       └── form.html.twig
└── partials/
    └── nav.html.twig
```

```php
// Dans un contrôleur
return $this->render('pages/tasks/index.html.twig', ['tasks' => $tasks]);
//                   ^--- relatif à src/MonSite/Templates/
```

### Créer le layout de base

Un layout commun définit la structure HTML partagée (head, navigation, footer) que chaque page vient compléter :

```twig
{# src/MonSite/Templates/layouts/base.html.twig #}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{% block title %}{{ app.name }}{% endblock %}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <nav>
        <a href="{{ path('tasks.index') }}">Tâches</a>
        {% if auth_check() %}
            {{ auth_user().getEmail() }}
            <a href="/logout">Déconnexion</a>
        {% else %}
            <a href="/login">Connexion</a>
        {% endif %}
    </nav>

    <main>
        {{ flashes() }}
        {% block content %}{% endblock %}
    </main>
</body>
</html>
```

### Étendre le layout dans une page

Chaque page étend le layout avec `extends` et vient remplir les blocs définis (`title`, `content`, etc.) :

```twig
{# src/MonSite/Templates/pages/tasks/index.html.twig #}
{% extends 'layouts/base.html.twig' %}

{% block title %}Mes tâches{% endblock %}

{% block content %}
    <h1>Mes tâches</h1>

    <a href="{{ path('tasks.new') }}">Nouvelle tâche</a>

    <ul>
        {% for task in tasks %}
            <li>{{ task.getTitle() }}</li>
        {% else %}
            <li>Aucune tâche.</li>
        {% endfor %}
    </ul>
{% endblock %}
```

### Afficher un formulaire dans une vue

Le système de formulaires expose des fonctions Twig natives, avec plusieurs niveaux de granularité selon le contrôle souhaité sur le rendu. Le token CSRF est inclus automatiquement.

```twig
{# src/MonSite/Templates/pages/tasks/form.html.twig #}
{% extends 'layouts/base.html.twig' %}

{% block content %}
    <h1>Nouvelle tâche</h1>

    {# Rendu complet automatique #}
    {{ form(form, path('tasks.create')) }}

    {# Ou rendu champ par champ #}
    {{ form_start(form, path('tasks.create')) }}
        {{ form_row(form, 'title') }}
        <button type="submit">Créer</button>
    {{ form_end() }}

    {# Ou rendu granulaire #}
    {{ form_start(form, path('tasks.create')) }}
        <div class="field">
            {{ form_label(form, 'title') }}
            {{ form_widget(form, 'title') }}
            {{ form_errors(form, 'title') }}
        </div>
        <button type="submit">Créer</button>
    {{ form_end() }}
{% endblock %}
```

### Fonctions et variables globales

| Élément | Description |
|---------|-------------|
| `app.name`, `app.version` | Valeurs de la section `general` dans `app.config.php` |
| `app.session.get('clé')` | Lecture d'une valeur de session |
| `app.cookie.get('clé')` | Lecture d'un cookie |
| `path('nom.route')` | Génère l'URL d'une route nommée |
| `path('nom.route', {id: 1})` | Génère l'URL avec paramètres |
| `asset('css/app.css')` | URL versionnée d'un asset compilé |
| `csrf_token()` | Token CSRF (à inclure dans chaque formulaire POST) |
| `flashes()` | Rendu HTML des messages flash en attente |
| `auth_check()` | `true` si l'utilisateur est connecté |
| `auth_user()` | Objet utilisateur courant |
| `auth_has_role('admin')` | `true` si l'utilisateur possède le rôle |
| `translate('clé')` | Traduction d'une clé |

### Compiler les assets CSS/JS

Placez vos fichiers CSS et JS dans `src/MonSite/Assets/` puis recompilez-les à chaque modification :

```bash
php bin/neo asset:reload --project=MonSite
```

---

## 8. Base de données et ORM

### Créer la base de données

```bash
php bin/neo database:create --project=MonSite
```

### Générer une entité

Une entité représente une table en base sous forme de classe PHP. La commande est interactive : elle vous demande les propriétés et leurs types.

```bash
php bin/neo make:entity Task --project=MonSite
```

Exemple de propriétés à saisir : `title` (string), `done` (boolean).

Deux fichiers sont générés :

```php
// src/MonSite/Database/Entity/Task.php
use Neo\Core\Database\ORM\Mapping\Attribute\Entity;
use Neo\Core\Database\ORM\Mapping\Attribute\Table;
use Neo\Core\Database\ORM\Mapping\Attribute\Id;
use Neo\Core\Database\ORM\Mapping\Attribute\GeneratedValue;
use Neo\Core\Database\ORM\Mapping\Attribute\Column;

#[Entity]
#[Table(name: 'tasks')]
class Task
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;

    #[Column(type: 'string')]
    private string $title;

    #[Column(type: 'boolean')]
    private bool $done = false;

    public function getId(): int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function isDone(): bool { return $this->done; }
    public function setDone(bool $done): void { $this->done = $done; }
}
```

```php
// src/MonSite/Database/Repository/TaskRepository.php
use Neo\Core\Database\ORM\Persistence\EntityRepository;

class TaskRepository extends EntityRepository
{
    // findAll(), find(), findBy(), findOneBy() disponibles par défaut
}
```

### Migrations

Une migration traduit les changements d'une entité en instructions SQL. Le flux habituel : générer, vérifier, puis appliquer.

```bash
# Aperçu sans écrire de fichier
php bin/neo database:orm:diff --project=MonSite --name=create_tasks_table --dry-run

# Génère le fichier de migration
php bin/neo database:orm:diff --project=MonSite --name=create_tasks_table

# Applique toutes les migrations en attente
php bin/neo database:migration:migrate --project=MonSite
```

### L'EntityManager

L'`EntityManager` centralise la persistance (création, modification, suppression) et est injecté automatiquement via le constructeur ou récupéré depuis le conteneur :

```php
use Neo\Core\Database\ORM\Persistence\EntityManager;

final class TaskController extends AbstractController
{
    public function __construct(
        private EntityManager $em,
        private TaskRepository $taskRepository
    ) {}

    #[Route(path: '/new', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        $task = new Task();
        $task->setTitle($this->getRequest()->body('title'));
        $task->setDone(false);

        $this->em->persist($task);
        $this->em->flush();

        $this->getFlash()->add('success', 'Tâche créée.');
        return $this->redirect('/tasks');
    }

    #[Route(path: '/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id): Response
    {
        $task = $this->taskRepository->find($id);
        $this->em->remove($task);
        $this->em->flush();

        $this->getFlash()->add('success', 'Tâche supprimée.');
        return $this->redirect('/tasks');
    }
}
```

### Méthodes de l'EntityManager

| Méthode | Description |
|---------|-------------|
| `persist($entity)` | Enregistre une entité pour insertion |
| `remove($entity)` | Marque une entité pour suppression |
| `flush()` | Exécute toutes les opérations en base |
| `find(Task::class, $id)` | Recherche par identifiant |
| `getRepository(Task::class)` | Retourne le repository associé |
| `wrapInTransaction(fn)` | Exécute un callback dans une transaction |

### Requêtes personnalisées du repository

Chaque entité possède un repository dédié, où l'on peut ajouter des méthodes de requête métier via le query builder :

```php
class TaskRepository extends EntityRepository
{
    public function findPending(): array
    {
        return $this->createQueryBuilder()
            ->where('done', '=', false)
            ->orderBy('id', 'DESC')
            ->getResults();
    }
}
```

---

## 9. Formulaires

Le framework dispose d'un système de formulaires complet via `FormFactory`, `FormBuilder` et `FormRenderer`. Il gère la création des champs, la validation, le mapping vers une entité et l'inclusion automatique du token CSRF.

### Traiter le formulaire dans le contrôleur

```php
use Neo\Core\Database\Form\FormFactory;
use Neo\Core\Database\ORM\Persistence\EntityManager;

#[MainRoute(path: '/tasks', name: 'tasks')]
final class TaskController extends AbstractController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private FormFactory $formFactory,
        private EntityManager $em
    ) {}

    // Formulaire de création (GET + POST sur la même route)
    #[Route(path: '/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(): Response
    {
        $builder = $this->formFactory->create('task');
        $builder
            ->add('title', 'text', ['required' => true, 'maxLength' => 100])
            ->add('done', 'checkbox', ['required' => false]);

        $form = $builder->getForm();
        $form->handleRequest($_POST);

        if ($form->isSubmitted() && $form->isValid()) {
            $task = new Task();
            $task->setTitle($form->getData()['title']);
            $task->setDone(false);

            $this->em->persist($task);
            $this->em->flush();

            $this->getFlash()->add('success', 'Tâche créée.');
            return $this->redirect('/tasks');
        }

        return $this->render('pages/tasks/form.html.twig', ['form' => $form]);
    }

    // Formulaire d'édition lié à une entité existante
    #[Route(path: '/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        $task = $this->taskRepository->find($id);

        // createFor() pré-remplit le formulaire depuis les getters de l'entité
        $builder = $this->formFactory->createFor($task, 'edit_task');
        $builder->add('title', 'text', ['required' => true]);

        $form = $builder->getForm();
        $form->handleRequest($_POST);

        if ($form->isSubmitted() && $form->isValid()) {
            // Les données sont mappées automatiquement vers $task via les setters
            $this->em->flush();

            $this->getFlash()->add('success', 'Tâche mise à jour.');
            return $this->redirect('/tasks');
        }

        return $this->render('pages/tasks/edit.html.twig', ['form' => $form]);
    }
}
```

### Afficher le formulaire dans le template

Trois niveaux de rendu sont possibles, du plus automatique au plus granulaire :

```twig
{% extends 'layouts/base.html.twig' %}

{% block content %}
    {# Rendu complet automatique (inclut le CSRF, les labels, les erreurs) #}
    {{ form(form, path('tasks.new')) }}

    {# Rendu champ par champ #}
    {{ form_start(form, path('tasks.new')) }}
        {{ form_row(form, 'title') }}
        {{ form_row(form, 'done') }}
        <button type="submit">Enregistrer</button>
    {{ form_end() }}

    {# Rendu granulaire : label, widget et erreurs séparés #}
    {{ form_start(form, path('tasks.new')) }}
        <div class="field">
            {{ form_label(form, 'title') }}
            {{ form_widget(form, 'title') }}
            {{ form_errors(form, 'title') }}
        </div>
        <button type="submit">Enregistrer</button>
    {{ form_end() }}
{% endblock %}
```

### Types de champs disponibles

| Type | HTML généré |
|------|-------------|
| `text` | `<input type="text">` |
| `email` | `<input type="email">` |
| `password` | `<input type="password">` |
| `textarea` | `<textarea>` |
| `number` | `<input type="number">` |
| `checkbox` | `<input type="checkbox">` |
| `select` | `<select>` |
| `date` | `<input type="date">` |
| `hidden` | `<input type="hidden">` |

### Fonctions Twig des formulaires

| Fonction | Description |
|----------|-------------|
| `form(form, action)` | Rendu complet du formulaire |
| `form_start(form, action)` | Balise ouvrante `<form>` + CSRF |
| `form_end()` | Balise fermante `</form>` |
| `form_row(form, 'champ')` | Label + widget + erreurs |
| `form_label(form, 'champ')` | Label uniquement |
| `form_widget(form, 'champ')` | Input uniquement |
| `form_errors(form, 'champ')` | Erreurs de validation uniquement |

---

## 10. Authentification

### Configuration

Le mode `session` convient aux applications web classiques ; le mode `token` (JWT) est adapté aux API sans état.

```php
// src/MonSite/Config/auth.config.php
return [
    'enabled'    => true,
    'guard'      => 'session',   // 'session' ou 'token' (JWT)
    'model'      => \Neo\Src\MonSite\Database\Entity\User::class,
    'identifier' => 'email',
    'password'   => 'password',
    'options' => [
        'timeout' => 3600,       // déconnexion après inactivité (secondes)
    ],
];
```

Pour JWT, remplacez `guard` par `'token'` et ajoutez :

```php
'options' => [
    'secret'     => 'votre-cle-secrete',
    'expiration' => 3600,
    'algorithm'  => 'HS256',
],
```

### Générer l'entité User

```bash
php bin/neo make:entity User --project=MonSite
# Propriétés : email (string), password (string), role (string)
```

### Connexion et inscription

```php
use Neo\Core\Security\Middleware\Attribute\Middleware;
use Neo\Core\Security\Middleware\Default\CsrfMiddleware;

#[MainRoute(path: '/', name: 'auth')]
final class AuthController extends AbstractController
{
    public function __construct(private UserRepository $userRepository) {}

    #[Route(path: '/login', name: 'login', methods: ['GET'])]
    public function loginForm(): Response
    {
        return $this->render('pages/auth/login.html.twig');
    }

    #[Middleware(use: CsrfMiddleware::class)]
    #[Route(path: '/login', name: 'login.post', methods: ['POST'])]
    public function login(): Response
    {
        $success = $this->auth()->attempt([
            'email'    => $this->getRequest()->body('email'),
            'password' => $this->getRequest()->body('password'),
        ]);

        if (!$success) {
            $this->getFlash()->add('error', 'Identifiants invalides.');
            return $this->redirect('/login');
        }

        return $this->redirect('/tasks');
    }

    #[Middleware(use: CsrfMiddleware::class)]
    #[Route(path: '/register', name: 'register.post', methods: ['POST'])]
    public function register(EntityManager $em): Response
    {
        $user = new User();
        $user->setEmail($this->getRequest()->body('email'));
        $user->setPassword($this->getPasswordManager()->hash(
            $this->getRequest()->body('password')
        ));
        $user->setRole('user');

        $em->persist($user);
        $em->flush();

        $this->auth()->login($user);
        return $this->redirect('/tasks');
    }

    #[Route(path: '/logout', name: 'logout', methods: ['POST'])]
    public function logout(): Response
    {
        $this->auth()->logout();
        return $this->redirect('/login');
    }
}
```

### Méthodes d'authentification

| Méthode | Description |
|---------|-------------|
| `$this->auth()->attempt([...])` | Tentative de connexion par identifiants |
| `$this->auth()->login($user)` | Connexion directe d'un objet utilisateur |
| `$this->auth()->logout()` | Déconnexion |
| `$this->auth()->check()` | `true` si connecté |
| `$this->auth()->user()` | Objet utilisateur courant |
| `$this->auth()->hasRole('admin')` | Vérification de rôle |
| `$this->getPasswordManager()->hash($plain)` | Hachage bcrypt |
| `$this->getPasswordManager()->verify($plain, $hash)` | Vérification |

---

## 11. Middlewares

Un middleware est une vérification exécutée avant le contrôleur. Il retourne `true` pour laisser passer la requête, `false` pour la bloquer.

### Générer un middleware

```bash
php bin/neo make:middleware AdminOnly --project=MonSite
```

Cela crée `src/MonSite/App/Middlewares/AdminOnlyMiddleware.php` :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\MonSite\App\Middlewares;

use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;

class AdminOnlyMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthManager $auth) {}

    public function handle(): bool
    {
        return $this->auth->hasRole('admin');
    }
}
```

Les dépendances du constructeur sont injectées automatiquement par le conteneur DI.

### Appliquer un middleware

L'attribut `#[Middleware]` est répétable et peut se placer sur la classe (toutes les routes du contrôleur) ou sur une méthode (une seule route).

```php
use Neo\Core\Security\Middleware\Attribute\Middleware;
use Neo\Core\Security\Middleware\Attribute\IsGranted;
use Neo\Core\Security\Middleware\Default\AuthMiddleware;
use Neo\Core\Security\Middleware\Default\CsrfMiddleware;

// Protège toutes les routes du contrôleur
#[Middleware(use: AuthMiddleware::class, redirect: 'auth.login')]
#[MainRoute(path: '/tasks', name: 'tasks')]
final class TaskController extends AbstractController { ... }

// Protège une seule méthode
#[Middleware(use: CsrfMiddleware::class)]
#[Route(path: '/new', name: 'create', methods: ['POST'])]
public function create(): Response { ... }

// Restriction par rôle (raccourci)
#[IsGranted(roles: ['admin'], redirect: 'auth.login')]
#[Route(path: '/admin', name: 'admin')]
public function admin(): Response { ... }
```

### Options de l'attribut `#[Middleware]`

| Paramètre | Défaut | Description |
|-----------|--------|-------------|
| `use` | — | Classe du middleware |
| `message` | `''` | Message en cas d'échec |
| `onError` | `'block'` | `'block'` (403) ou `'soft'` (avertissement, laisse passer) |
| `redirect` | `null` | Nom de route de redirection si échec |
| `params` | `[]` | Paramètres supplémentaires pour le constructeur |
| `priority` | `0` | Ordre d'exécution (décroissant) |

### Middlewares intégrés

| Classe | Description |
|--------|-------------|
| `AuthMiddleware` | Vérifie que l'utilisateur est connecté |
| `GuestMiddleware` | Vérifie que l'utilisateur n'est pas connecté |
| `CsrfMiddleware` | Valide le token CSRF (POST/PUT/PATCH/DELETE) |
| `IsGrantedMiddleware` | Vérifie un ou plusieurs rôles (logique OU) |
| `RoleMiddleware` | Vérifie un rôle unique via `params` |
| `RateLimitMiddleware` | Limite les requêtes par IP et chemin |
| `AuthRateLimitMiddleware` | Limite les tentatives de connexion par IP + email |

---

## 12. Référence des commandes CLI

Récapitulatif de toutes les commandes `php bin/neo` disponibles, regroupées par usage.

### Gestion de projet

| Commande | Description |
|----------|-------------|
| `project:create <Nom>` | Créer un nouveau projet |
| `project:create <Nom> --skeleton` | Créer avec structure minimale |
| `app:serve <Nom>` | Démarrer le serveur PHP intégré |

### Génération de code

| Commande | Description |
|----------|-------------|
| `make:controller <Nom> --project=X` | Générer un contrôleur |
| `make:controller <Nom> --api --project=X` | Contrôleur API (JSON uniquement) |
| `make:entity <Nom> --project=X` | Générer une entité et son repository |
| `make:middleware <Nom> --project=X` | Générer un middleware |
| `make:event <Nom> --project=X` | Générer un événement |
| `make:cron <Nom> --project=X` | Générer une tâche planifiée |
| `app:make:command <Nom> --project=X` | Générer une commande CLI |

### Base de données et migrations

| Commande | Description |
|----------|-------------|
| `database:create --project=X` | Créer la base de données |
| `database:orm:diff --project=X --name=<nom>` | Générer une migration |
| `database:orm:diff --project=X --name=<nom> --dry-run` | Aperçu sans écriture |
| `database:migration:migrate --project=X` | Appliquer les migrations |
| `database:migration:rollback --project=X` | Annuler la dernière migration |
| `database:migration:status --project=X` | Statut des migrations |

### Utilitaires

| Commande | Description |
|----------|-------------|
| `debug:router --project=X` | Lister toutes les routes enregistrées |
| `cache:clear --project=X` | Vider le cache |
| `asset:reload --project=X` | Recompiler les assets CSS/JS |
| `translation:sync --project=X` | Synchroniser les clés de traduction |