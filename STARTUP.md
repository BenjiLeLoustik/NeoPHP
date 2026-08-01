# Start Up

This guide walks you through building your first NeoPHP application from scratch. It assumes basic knowledge of PHP and the command line.

---

## Table of contents

1. [Prerequisites](#1-prerequisites)
2. [Installing the framework](#2-installing-the-framework)
3. [Creating a new project](#3-creating-a-new-project)
4. [Starting the development server](#4-starting-the-development-server)
5. [Configuration files](#5-configuration-files)
6. [Routes and controllers](#6-routes-and-controllers)
7. [Views with Twig](#7-views-with-twig)
8. [Database and ORM](#8-database-and-orm)
9. [Forms](#9-forms)
10. [Authentication](#10-authentication)
11. [Middlewares](#11-middlewares)
12. [CLI command reference](#12-cli-command-reference)

---

## 1. Prerequisites

| Tool | Minimum version |
|-------|-----------------|
| PHP | 8.5 |
| Composer | 2.x |
| Git | — |
| MySQL / MariaDB | — (optional) |

Required PHP extensions: `pdo`, `zip`, `curl`, `fileinfo`, `dom`, `iconv`.

Quick check:

```bash
php -v
php -m | grep -E "pdo|zip|curl|fileinfo"
composer -V
```

---

## 2. Installing the framework

Clone the NeoPHP repository, then install the PHP dependencies via Composer:

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

## 3. Creating a new project

NeoPHP can host one or more independent projects in the `src/` folder. The following command automatically generates the entire folder structure needed for a new site:

```bash
php bin/neo project:create MySite
```

### Generated structure

Each project is self-contained, gathering its code, templates, configuration, and assets:

```
src/MySite/
├── App/
│   ├── Controllers/        your controllers
│   ├── Middlewares/        your middlewares
│   └── Services/           your business services
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
│   ├── Entity/             your entities (models)
│   ├── Migrations/         SQL migration files
│   └── Repository/         data access
├── Storage/                logs, cache, sessions
├── Templates/              your Twig templates
└── Translations/           translation files
```

---

## 4. Starting the development server

NeoPHP ships with PHP's built-in server, handy for developing locally without configuring Apache or Nginx:

```bash
php bin/neo app:serve MySite
```

The site is available at **http://localhost:8000**.

> The HTTP entry point is `public/index.php`. In production, configure Apache or Nginx to point the web root at this `public/` folder.

---

## 5. Configuration files

Each project has its own `Config/` folder, with a dedicated file per domain (application, database, template engine, etc.). Here are the three files to know to get started.

### app.config.php

The project's main configuration file. The `access` key determines which project is served based on the HTTP domain called, which lets you host several sites side by side.

```php
// src/MySite/Config/app.config.php
return [
    'general' => [
        'name'    => 'MySite',
        'version' => '1.0.0',
    ],
    'environment' => 'dev',         // 'dev' or 'prod'
    'access'      => 'localhost:8000', // must match the access URL
    'date' => [
        'timezone' => 'Europe/Paris',
    ],
];
```

The `general` section is available in every template via the `app` global variable:

```twig
<title>{{ app.name }}</title>
```

### database.config.php

Defines the connection(s) available to the project. Several connections can be declared under `connections`, with the `use` key indicating the default one.

```php
// src/MySite/Config/database.config.php
return [
    'enabled' => true,
    'use'     => 'default',
    'connections' => [
        'default' => [
            'driver'  => 'mysql',
            'host'    => 'localhost',
            'port'    => 3306,
            'dbname'  => 'mysite',
            'user'    => 'root',
            'pass'    => '',
            'charset' => 'utf8mb4',
        ],
    ],
];
```

### twig.config.php

Twig rendering settings. Remember to enable `cache` and disable `debug` before going to production.

```php
// src/MySite/Config/twig.config.php
return [
    'cache'            => false,  // true in production
    'debug'            => true,
    'auto_reload'      => true,
    'auto_escape'      => 'html',
    'charset'          => 'UTF-8',
    'strict_variables' => false,
];
```

---

## 6. Routes and controllers

### Generating a controller

```bash
php bin/neo make:controller TaskController --project=MySite
```

This creates `src/MySite/App/Controllers/TaskController.php`.

### Declaring routes via attributes

Routes are declared via PHP attributes directly on classes and methods, with no separate routing file. `#[MainRoute]` defines a path and name prefix for the whole controller; `#[Route]` declares each individual route.

```php
<?php
declare(strict_types=1);

namespace Neo\Src\MySite\App\Controllers;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Http\Response\Types\Response;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;
use Neo\Src\MySite\Database\Repository\TaskRepository;

#[MainRoute(path: '/tasks', name: 'tasks')]
final class TaskController extends AbstractController
{
    public function __construct(
        private TaskRepository $taskRepository
    ) {}

    // GET /tasks/  → name: tasks.index
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/tasks/index.html.twig', [
            'tasks' => $this->taskRepository->findAll(),
        ]);
    }

    // GET /tasks/new  → name: tasks.new
    #[Route(path: '/new', name: 'new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->render('pages/tasks/form.html.twig');
    }

    // POST /tasks/new  → name: tasks.create
    #[Route(path: '/new', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        // persistence via FormFactory — see the Forms section
        $this->getFlash()->add('success', 'Task created.');
        return $this->redirect('/tasks');
    }

    // GET /tasks/{id}  → dynamic parameter
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
        // deletion — see the Database section
        $this->getFlash()->add('success', 'Task deleted.');
        return $this->redirect('/tasks');
    }
}
```

### Reading request data

```php
// GET parameters
$page = $this->getRequest()->query('page', 1);

// POST or JSON body
$title = $this->getRequest()->body('title');

// Headers
$token = $this->getRequest()->header('Authorization');
```

### Building a response

A controller can return a Twig view, a redirect, or a JSON response:

```php
// Rendering a Twig template
return $this->render('pages/index.html.twig', ['data' => $data]);

// Redirect
return $this->redirect('/tasks');

// JSON
return $this->json(['status' => 'ok']);
return $this->jsonSuccess(['id' => 42]);
return $this->jsonError('Not found', 404);
```

### Checking registered routes

Handy for checking that a route is properly registered and knowing its exact name:

```bash
php bin/neo debug:router --project=MySite
```

---

## 7. Views with Twig

### Template location

Templates live in `src/<Project>/Templates/`. The path passed to `render()` is **relative to this folder**.

```
src/MySite/Templates/
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
// In a controller
return $this->render('pages/tasks/index.html.twig', ['tasks' => $tasks]);
//                   ^--- relative to src/MySite/Templates/
```

### Creating the base layout

A common layout defines the shared HTML structure (head, navigation, footer) that each page then fills in:

```twig
{# src/MySite/Templates/layouts/base.html.twig #}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{% block title %}{{ app.name }}{% endblock %}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <nav>
        <a href="{{ path('tasks.index') }}">Tasks</a>
        {% if auth_check() %}
            {{ auth_user().getEmail() }}
            <a href="/logout">Log out</a>
        {% else %}
            <a href="/login">Log in</a>
        {% endif %}
    </nav>

    <main>
        {{ flashes() }}
        {% block content %}{% endblock %}
    </main>
</body>
</html>
```

### Extending the layout in a page

Each page extends the layout with `extends` and fills in the defined blocks (`title`, `content`, etc.):

```twig
{# src/MySite/Templates/pages/tasks/index.html.twig #}
{% extends 'layouts/base.html.twig' %}

{% block title %}My tasks{% endblock %}

{% block content %}
    <h1>My tasks</h1>

    <a href="{{ path('tasks.new') }}">New task</a>

    <ul>
        {% for task in tasks %}
            <li>{{ task.getTitle() }}</li>
        {% else %}
            <li>No tasks.</li>
        {% endfor %}
    </ul>
{% endblock %}
```

### Displaying a form in a view

The form system exposes native Twig functions, with several levels of granularity depending on how much control you want over the rendering. The CSRF token is included automatically.

```twig
{# src/MySite/Templates/pages/tasks/form.html.twig #}
{% extends 'layouts/base.html.twig' %}

{% block content %}
    <h1>New task</h1>

    {# Full automatic rendering #}
    {{ form(form, path('tasks.create')) }}

    {# Or field-by-field rendering #}
    {{ form_start(form, path('tasks.create')) }}
        {{ form_row(form, 'title') }}
        <button type="submit">Create</button>
    {{ form_end() }}

    {# Or granular rendering #}
    {{ form_start(form, path('tasks.create')) }}
        <div class="field">
            {{ form_label(form, 'title') }}
            {{ form_widget(form, 'title') }}
            {{ form_errors(form, 'title') }}
        </div>
        <button type="submit">Create</button>
    {{ form_end() }}
{% endblock %}
```

### Functions and global variables

| Element | Description |
|---------|-------------|
| `app.name`, `app.version` | Values from the `general` section of `app.config.php` |
| `app.session.get('key')` | Reads a session value |
| `app.cookie.get('key')` | Reads a cookie |
| `path('route.name')` | Generates the URL of a named route |
| `path('route.name', {id: 1})` | Generates the URL with parameters |
| `asset('css/app.css')` | Versioned URL of a compiled asset |
| `csrf_token()` | CSRF token (to include in every POST form) |
| `flashes()` | HTML rendering of pending flash messages |
| `auth_check()` | `true` if the user is logged in |
| `auth_user()` | Current user object |
| `auth_has_role('admin')` | `true` if the user has the role |
| `translate('key')` | Translation of a key |

### Compiling CSS/JS assets

Put your CSS and JS files in `src/MySite/Assets/`, then recompile them after every change:

```bash
php bin/neo asset:reload --project=MySite
```

---

## 8. Database and ORM

### Creating the database

```bash
php bin/neo database:create --project=MySite
```

### Generating an entity

An entity represents a database table as a PHP class. The command is interactive: it asks you for the properties and their types.

```bash
php bin/neo make:entity Task --project=MySite
```

Example properties to enter: `title` (string), `done` (boolean).

Two files are generated:

```php
// src/MySite/Database/Entity/Task.php
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
// src/MySite/Database/Repository/TaskRepository.php
use Neo\Core\Database\ORM\Persistence\EntityRepository;

class TaskRepository extends EntityRepository
{
    // findAll(), find(), findBy(), findOneBy() available by default
}
```

### Migrations

A migration translates an entity's changes into SQL instructions. The usual flow: generate, check, then apply.

```bash
# Preview without writing a file
php bin/neo database:orm:diff --project=MySite --name=create_tasks_table --dry-run

# Generate the migration file
php bin/neo database:orm:diff --project=MySite --name=create_tasks_table

# Apply all pending migrations
php bin/neo database:migration:migrate --project=MySite
```

### The EntityManager

The `EntityManager` centralizes persistence (create, update, delete) and is automatically injected via the constructor or retrieved from the container:

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

        $this->getFlash()->add('success', 'Task created.');
        return $this->redirect('/tasks');
    }

    #[Route(path: '/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id): Response
    {
        $task = $this->taskRepository->find($id);
        $this->em->remove($task);
        $this->em->flush();

        $this->getFlash()->add('success', 'Task deleted.');
        return $this->redirect('/tasks');
    }
}
```

### EntityManager methods

| Method | Description |
|---------|-------------|
| `persist($entity)` | Registers an entity for insertion |
| `remove($entity)` | Marks an entity for deletion |
| `flush()` | Runs all pending database operations |
| `find(Task::class, $id)` | Lookup by identifier |
| `getRepository(Task::class)` | Returns the associated repository |
| `wrapInTransaction(fn)` | Runs a callback within a transaction |

### Custom repository queries

Each entity has a dedicated repository, where you can add business query methods via the query builder:

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

## 9. Forms

The framework has a full form system via `FormFactory`, `FormBuilder`, and `FormRenderer`. It handles field creation, validation, mapping to an entity, and automatically includes the CSRF token.

### Handling the form in the controller

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

    // Creation form (GET + POST on the same route)
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

            $this->getFlash()->add('success', 'Task created.');
            return $this->redirect('/tasks');
        }

        return $this->render('pages/tasks/form.html.twig', ['form' => $form]);
    }

    // Edit form bound to an existing entity
    #[Route(path: '/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        $task = $this->taskRepository->find($id);

        // createFor() pre-fills the form from the entity's getters
        $builder = $this->formFactory->createFor($task, 'edit_task');
        $builder->add('title', 'text', ['required' => true]);

        $form = $builder->getForm();
        $form->handleRequest($_POST);

        if ($form->isSubmitted() && $form->isValid()) {
            // Data is automatically mapped to $task via the setters
            $this->em->flush();

            $this->getFlash()->add('success', 'Task updated.');
            return $this->redirect('/tasks');
        }

        return $this->render('pages/tasks/edit.html.twig', ['form' => $form]);
    }
}
```

### Displaying the form in the template

Three levels of rendering are possible, from most automatic to most granular:

```twig
{% extends 'layouts/base.html.twig' %}

{% block content %}
    {# Full automatic rendering (includes CSRF, labels, errors) #}
    {{ form(form, path('tasks.new')) }}

    {# Field-by-field rendering #}
    {{ form_start(form, path('tasks.new')) }}
        {{ form_row(form, 'title') }}
        {{ form_row(form, 'done') }}
        <button type="submit">Save</button>
    {{ form_end() }}

    {# Granular rendering: label, widget, and errors separated #}
    {{ form_start(form, path('tasks.new')) }}
        <div class="field">
            {{ form_label(form, 'title') }}
            {{ form_widget(form, 'title') }}
            {{ form_errors(form, 'title') }}
        </div>
        <button type="submit">Save</button>
    {{ form_end() }}
{% endblock %}
```

### Available field types

| Type | Generated HTML |
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

### Form Twig functions

| Function | Description |
|----------|-------------|
| `form(form, action)` | Full form rendering |
| `form_start(form, action)` | Opening `<form>` tag + CSRF |
| `form_end()` | Closing `</form>` tag |
| `form_row(form, 'field')` | Label + widget + errors |
| `form_label(form, 'field')` | Label only |
| `form_widget(form, 'field')` | Input only |
| `form_errors(form, 'field')` | Validation errors only |

---

## 10. Authentication

### Configuration

`session` mode is suited to classic web applications; `token` mode (JWT) is suited to stateless APIs.

```php
// src/MySite/Config/auth.config.php
return [
    'enabled'    => true,
    'guard'      => 'session',   // 'session' or 'token' (JWT)
    'model'      => \Neo\Src\MySite\Database\Entity\User::class,
    'identifier' => 'email',
    'password'   => 'password',
    'options' => [
        'timeout' => 3600,       // logout after inactivity (seconds)
    ],
];
```

For JWT, replace `guard` with `'token'` and add:

```php
'options' => [
    'secret'     => 'your-secret-key',
    'expiration' => 3600,
    'algorithm'  => 'HS256',
],
```

### Generating the User entity

```bash
php bin/neo make:entity User --project=MySite
# Properties: email (string), password (string), role (string)
```

### Login and registration

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
            $this->getFlash()->add('error', 'Invalid credentials.');
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

### Authentication methods

| Method | Description |
|---------|-------------|
| `$this->auth()->attempt([...])` | Attempts login with credentials |
| `$this->auth()->login($user)` | Directly logs in a user object |
| `$this->auth()->logout()` | Logs out |
| `$this->auth()->check()` | `true` if logged in |
| `$this->auth()->user()` | Current user object |
| `$this->auth()->hasRole('admin')` | Role check |
| `$this->getPasswordManager()->hash($plain)` | Bcrypt hashing |
| `$this->getPasswordManager()->verify($plain, $hash)` | Verification |

---

## 11. Middlewares

A middleware is a check run before the controller. It returns `true` to let the request through, `false` to block it.

### Generating a middleware

```bash
php bin/neo make:middleware AdminOnly --project=MySite
```

This creates `src/MySite/App/Middlewares/AdminOnlyMiddleware.php`:

```php
<?php
declare(strict_types=1);

namespace Neo\Src\MySite\App\Middlewares;

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

Constructor dependencies are automatically injected by the DI container.

### Applying a middleware

The `#[Middleware]` attribute is repeatable and can be placed on the class (all of the controller's routes) or on a method (a single route).

```php
use Neo\Core\Security\Middleware\Attribute\Middleware;
use Neo\Core\Security\Middleware\Attribute\IsGranted;
use Neo\Core\Security\Middleware\Default\AuthMiddleware;
use Neo\Core\Security\Middleware\Default\CsrfMiddleware;

// Protects all of the controller's routes
#[Middleware(use: AuthMiddleware::class, redirect: 'auth.login')]
#[MainRoute(path: '/tasks', name: 'tasks')]
final class TaskController extends AbstractController { ... }

// Protects a single method
#[Middleware(use: CsrfMiddleware::class)]
#[Route(path: '/new', name: 'create', methods: ['POST'])]
public function create(): Response { ... }

// Role restriction (shortcut)
#[IsGranted(roles: ['admin'], redirect: 'auth.login')]
#[Route(path: '/admin', name: 'admin')]
public function admin(): Response { ... }
```

### `#[Middleware]` attribute options

| Parameter | Default | Description |
|-----------|--------|-------------|
| `use` | — | Middleware class |
| `message` | `''` | Message on failure |
| `onError` | `'block'` | `'block'` (403) or `'soft'` (warning, lets it through) |
| `redirect` | `null` | Redirect route name on failure |
| `params` | `[]` | Extra parameters for the constructor |
| `priority` | `0` | Execution order (descending) |

### Built-in middlewares

| Class | Description |
|--------|-------------|
| `AuthMiddleware` | Checks that the user is logged in |
| `GuestMiddleware` | Checks that the user is not logged in |
| `CsrfMiddleware` | Validates the CSRF token (POST/PUT/PATCH/DELETE) |
| `IsGrantedMiddleware` | Checks one or more roles (OR logic) |
| `RoleMiddleware` | Checks a single role via `params` |
| `RateLimitMiddleware` | Limits requests per IP and path |
| `AuthRateLimitMiddleware` | Limits login attempts per IP + email |

---

## 12. CLI command reference

Summary of all available `php bin/neo` commands, grouped by use case.

### Project management

| Command | Description |
|----------|-------------|
| `project:create <Name>` | Create a new project |
| `project:create <Name> --skeleton` | Create with a minimal structure |
| `app:serve <Name>` | Start the built-in PHP server |

### Code generation

| Command | Description |
|----------|-------------|
| `make:controller <Name> --project=X` | Generate a controller |
| `make:controller <Name> --api --project=X` | API controller (JSON only) |
| `make:entity <Name> --project=X` | Generate an entity and its repository |
| `make:middleware <Name> --project=X` | Generate a middleware |
| `make:event <Name> --project=X` | Generate an event |
| `make:cron <Name> --project=X` | Generate a scheduled task |
| `app:make:command <Name> --project=X` | Generate a CLI command |

### Database and migrations

| Command | Description |
|----------|-------------|
| `database:create --project=X` | Create the database |
| `database:orm:diff --project=X --name=<name>` | Generate a migration |
| `database:orm:diff --project=X --name=<name> --dry-run` | Preview without writing |
| `database:migration:migrate --project=X` | Apply migrations |
| `database:migration:rollback --project=X` | Roll back the last migration |
| `database:migration:status --project=X` | Migration status |

### Utilities

| Command | Description |
|----------|-------------|
| `debug:router --project=X` | List all registered routes |
| `cache:clear --project=X` | Clear the cache |
| `asset:reload --project=X` | Recompile CSS/JS assets |