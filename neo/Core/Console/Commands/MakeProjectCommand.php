<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;

#[Command(
    name: 'make:project',
    description: 'Créer un nouveau projet dans ./src/'
)]
final class MakeProjectCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:project';
    }

    public function getDescription(): string
    {
        return 'Créer un nouveau projet dans ./src/';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : make:project
Description : Crée un nouveau projet dans ./src/ avec une structure complète.

Usage :
  php bin/neo make:project <NomDuProjet> [option: --skeleton]

Arguments :
  NomDuProjet       Nom du projet à créer (ex : NeoAdmin)

Options :
  --skeleton        Crée uniquement les dossiers de base sans fichiers ni vues

Exemples :
  php bin/neo make:project NeoAdmin
    Crée un projet complet avec dossiers, controllers, vues, assets, config, etc.

  php bin/neo make:project MyProject --skeleton
    Crée seulement la structure minimale des dossiers dans ./src/MyProject

Notes :
- Les projets sont créés dans ./src/
- Les ports localhost sont automatiquement attribués pour éviter les conflits
- Les fichiers par défaut incluent :
  Controllers, Middlewares, Services, Views, Forms, Assets, Config, Model, Repository, Storage, Translations
HELP;
    }

    public function execute(array $args): void
    {
        $name = $args[0] ?? null;
        $skeleton = $args[1] ?? null;

        if (!$name) {
            echo "Usage : php bin/neo make:project <NomDuProjet> [option: --skeleton]\n";
            return;
        }

        if ($skeleton === null) {
            $skeleton = false;
        } else {
            $skeleton = true;
        }

        $originalName = $name;
        $name = $this->pascalCaseSlug($name);
        $path = ROOT_DIR . "/src/$name";

        if (is_dir($path)) {
            echo "Le projet '$name' existe déjà !\n";
            return;
        }

        [$host, $port] = $this->getAvailableHostPort();

        mkdir($path, 0777, true);
        $this->generateDirectories($path, $name, $originalName, $host, $port, $skeleton);

        $this->generateProjectComposer($path, $name);
        $this->registerInRootComposer($name);

        echo "Votre projet a bien été créé dans ./src/{$name} \n";
        echo "Vous pouvez lancer votre projet sur {$host}:{$port}\n";

        echo "Lancement de composer update...\n";
        $output = shell_exec("composer update 2>&1");
        echo $output . "\n";
        echo "Composer update terminé. \n";
    }

    private function generateDirectories(string $path, string $name, string $originalName, string $host, int $port, bool $skeleton = false): void
    {
        $directories = [
            "App/Controllers/",
            "App/Middlewares/",
            "App/Services/",
            "App/Views/",
            "App/Forms/",
            "Assets/",
            "Config/",
            "Model/",
            "Repository/",
            "Storage/",
            "Translations"
        ];

        foreach ($directories as $directory) {
            if (!file_exists($path . '/' . $directory)) {
                mkdir($path . '/' . $directory, 0777, true);
            }
        }

        $this->generateAppConfig("{$path}/Config/", $name, $host, $port);
        $this->generateDatabaseConfig("{$path}/Config/", $name);
        $this->generateDeployConfig("{$path}/Config/", $name);
        $this->generateLoggerConfig("{$path}/Config/", $name);
        $this->generateCacheConfig("{$path}/Config/", $name);
        $this->generateTwigConfig("{$path}/Config/", $name);
        $this->generateSessionConfig("{$path}/Config/", $name);
        $this->generateAPIConfig("{$path}/Config/", $name);

        $this->generateGitignore($path);

        if (!$skeleton) {
            $directories[] = "Translations/fr";
            $directories[] = "Translations/en";
            $directories[] = "Assets/css";
            $directories[] = "Assets/js";
            $directories[] = "App/Views/errors/";
            $directories[] = "App/Views/pages/";
            $directories[] = "App/Views/layouts/";
            $directories[] = "App/Views/pages/default/";
            $directories[] = "App/Views/partials/";

            foreach ($directories as $directory) {
                if (!file_exists($path . '/' . $directory)) {
                    mkdir($path . '/' . $directory, 0777, true);
                }
            }

            $this->generateDefaultController("{$path}/App/Controllers/", $name, $originalName);
            $this->generateDefaultLayoutView("{$path}/App/Views/layouts/", $name);
            $this->generateDefaultView("{$path}/App/Views/pages/default/", $name);
            $this->generateDefaultCss("{$path}/Assets/css/");
            $this->generateDefaultJs("{$path}/Assets/js/");
            $this->generateDefaultTranslations("{$path}/Translations/");
        }
    }

    private function generateDefaultController(string $path, string $name, string $originalName): void
    {
        $filename = 'DefaultController.php';
        $content = <<<PHP
<?php
declare(strict_types=1);

namespace Neo\\Src\\$name\\App\\Controllers;

use Neo\\Core\\Controller\\AbstractController;
use Neo\\Core\\Routing\\Attribute\\MainRoute;
use Neo\\Core\\Routing\\Attribute\\Route;
use Neo\\Core\\Http\\Response\\Response;
use Neo\\Core\\Http\\Response\\RedirectResponse;
use Neo\\Core\\Translation\\TranslationManager;

#[MainRoute(path: '/', name: 'default')]
final class DefaultController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return \$this->render('pages/default/index.html.twig', [
            'projectName' => "$name",
            'projectPath' => "$path"
        ]);   
    }
    
    #[Route(path: '/change-locale/{locale}', name: 'change.locale', methods: ['GET'])]
    public function changeLocal(
        string  \$locale,
        TranslationManager \$translationManager
    ): RedirectResponse {
        \$translationManager->setLocale(\$locale);
        return \$this->redirectToRoute('default.index');
    }
}

PHP;
        file_put_contents($path . $filename, $content);
    }

    private function generateDefaultLayoutView(string $path, string $name): void
    {
        $filename = 'base_layout.html.twig';
        $content = <<<TWIG
{# ./src/$name/App/Views/layouts/base_layout.html.twig #}

<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{% block title %}{% endblock %}</title>
    
        {# Styles globaux #}
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        {% block stylesheets %}{% endblock %}
    </head>
    <body class="layout-body">
    
        <header class="layout-header">
            {% block header %}
                <h1>{{ translate('welcome.header.title') }}</h1>
                <p class="subtitle">
                    {{ translate('welcome.header.sub_title') }}
                </p>
                <div class="locales">
                    {% for locale, name in getLocales() %}
                        <a class="{% if locale == getLocale() %}currentLocale{% endif %}" href="{{ path('default.change.locale', {'locale': locale}) }}">
                            {{ translate('welcome.' ~ name) }}
                        </a>
                    {% endfor %}
                </div>
            {% endblock %}
        </header>
        
        <main class="layout-main">
            {% block content %}{% endblock %}
        </main>

        <footer class="layout-footer">
            {% block footer %}
                <p>&copy; {{ "now"|date("Y") }} - NeoPHP</p>
            {% endblock %}
        </footer>
        
        <script src="{{ asset('js/app.js') }}"></script>
        {% block javascripts %}{% endblock %}
    
    </body>
</html>

TWIG;

        file_put_contents($path . $filename, $content);
    }

    private function generateDefaultView(string $path, string $name): void
    {
        $filename = 'index.html.twig';
        $content = <<<TWIG
{# ./src/$name/App/Views/pages/default/index.html.twig #}

{% extends 'layouts/base_layout.html.twig' %}

{% block title %}{{ translate('welcome.page_title') }}{% endblock %}

{% block content %}
    <section class="landing-section">
        <h2>{{ translate('welcome.title.message', '', {'projectName': projectName}) }}</h2>
        <p class="intro">
            {{ translate('welcome.title.install') }}
        </p>
        <p class="note">
            {{ translate('welcome.title.note') }}
        </p>

        <div class="actions">
            <div class="action-block">
                <p>{{ translate('welcome.command.create_crud') }}</p>
                <code>php bin/neo make:crud &lt;Entity&gt; --project={{ app.name }}</code>
            </div>
            <div class="action-block">
                <p>{{ translate('welcome.command.create_controller') }}</p>
                <code>php bin/neo make:controller &lt;ControllerName&gt; --project={{ app.name }}</code>
            </div>
        </div>
    </section>

{% endblock %}

TWIG;
        file_put_contents($path . $filename, $content);
    }

    private function generateAppConfig(string $path, string $name, string $host, int $port): void
    {
        $filename = 'app.config.php';
        $content = <<<PHP
<?php
declare(strict_types=1);

// ./src/$name/Config/app.config.php

return [
    'general' => [
        'name' => "$name",
        'description' => "Votre projet NeoPHP",
    ],
    
    'environment' => "dev",
        
    'access' => "$host:$port",
        
    'date' => [
        'timezone' => 'Europe/Paris',
    ],
    
    'translation' => [
        'enabled' => true,
        'default_locale' => 'fr',
        'available_locales' => [
            'fr' => 'Français',
            'en' => 'Anglais'
        ]
    ],
    
    'auth' => [
        'enabled'    => false,
        'model'      => '', // ex: \Neo\Src\{$name}\Model\UserAccount::class
        'identifier' => '',    // champ utilisé pour se connecter
        'password'   => '', // champ mot de passe hashé
        'guard'      => 'session',  // session ou token (API)
        'role'       => '',     // champ du modèle qui contient le/les rôle(s)
        'routes' => [
            'login'    => '',   // route de redirection si non connecté
            'logout'   => '', // route de deconnexion
            'home'     => '' // route après connexion réussie
        ],
    ],

];

PHP;

        file_put_contents($path . $filename, $content);
    }

    private function generateDatabaseConfig(string $path, string $name): void
    {
        $filename = 'database.config.php';
        $content = <<<PHP
<?php
declare(strict_types=1);

// ./src/$name/Config/database.config.php

return [

    'enabled' => false,
    'use' => "default",

    'connections' => [

        'default' => [
            'driver' => "mysql",
            'host' => "localhost",
            'port' => 3306,
            'user' => "",
            'pass' => "",
            'dbname' => "",
            'charset' => "utf8mb4",
            'prefix' => ""
        ]

    ]

];
PHP;

        file_put_contents($path . $filename, $content);
    }

    private function generateDeployConfig(string $path, string $name): void
    {
        $filename = 'deploy.config.php';
        $content = <<<PHP
<?php
declare(strict_types=1);

// ./src/$name/Config/deploy.config.php

return [
    'ftp' => [
        'host' => '',
        'user' => '',
        'pass' => ''
    ],
    'remote' => [
        'domain' => '', // exemple : your-app.fr
        'framework_dir' => '', // exemple : domains/your-app.fr/neo/
        'public_dir' => '' // exemple : domains/your-app.fr/public_html
    ]
];
PHP;

        file_put_contents($path . $filename, $content);
    }

    private function generateLoggerConfig(string $path, string $name): void
    {
        $filename = 'logger.config.php';
        $content = <<<PHP
<?php
declare(strict_types=1);

// ./src/$name/Config/logger.config.php

return [
    'enabled' => false,

    'channels' => [
        'framework' => [
            'enabled' => false,
            'name' => 'framework',
            'extension' => 'log'
        ]
    ],

    'rotation' => [
        'enabled' => true,
        'type' => 'daily',
        'max_file_size' => 5 * 1024 * 1024,
    ],

    'archive' => [
        'enabled' => true,
        'extension' => 'zip'
    ],

    'log_format' => "[{%datetime%}][{%level_name%}][{%level_code%}] [{%origin%}] {%message%} {%context%}",
    'min_level' => 'DEBUG',
];
PHP;

        file_put_contents($path . $filename, $content);
    }

    private function generateCacheConfig(string $path, string $name): void
    {
        $filename = 'cache.config.php';
        $content = <<<PHP
<?php
declare(strict_types=1);

// ./src/$name/Config/cache.config.php

return [

    'enabled' => true,
    'driver' => 'files',
    'ttl' => 3600,
    'storage' => 'cache'

];
PHP;

        file_put_contents($path . $filename, $content);
    }

    private function generateTwigConfig(string $path, string $name): void
    {
        $filename = 'twig.config.php';
        $content = <<<PHP
<?php
declare(strict_types=1);

// ./src/$name/Config/twig.config.php

return [

    'cache' => true,
    'debug' => true,
    'auto_reload' => true,
    'auto_escape' => 'html',
    'charset' => 'UTF-8',
    'strict_variables' => false,

    'options' => [
        'optimizations' => -1,
    ]

];
PHP;

        file_put_contents($path . $filename, $content);
    }

    private function generateSessionConfig(string $path, string $name): void
    {
        $configSessionName = strtoupper($name);
        $configCookieName = strtolower($name);
        $configFlashName = strtolower($name);

        $filename = 'session.config.php';
        $content = <<<PHP
<?php
declare(strict_types=1);

// ./src/$name/Config/session.config.php

return [

    'session' => [
        'enabled' => true,
        'name' => '{$configSessionName}_SESSION',
        'lifetime' => 3600,
        'path' => '/',
        'domain' => null,
        'secure' => false,
        'http_only' => true,
        'same_site' => 'Lax',

        'storage' => [
            'enabled' => true,
            'handler' => 'files'
        ]
    ],

    'cookie' => [
        'prefix' => '{$configCookieName}_',
        'path' => '/',
        'domain' => null,
        'secure' => false,
        'http_only' => true,
        'same_site' => 'Lax',
        'lifetime' => 86400 * 30,
    ],

    'flash' => [
        'session_key' => '_flash_{$configFlashName}',
        'types' => [ 'success', 'error', 'warning', 'info' ],
        'auto_expire' => true,
    ]

];
PHP;

        file_put_contents($path . $filename, $content);
    }

    private function generateAPIConfig(string $path, string $name): void
    {
        $filename = 'api.config.php';
        $content = <<<PHP
<?php
declare(strict_types=1);

// ./src/$name/Config/api.config.php

return [

    // Exemple :
    // 'stripe' => [
    //     'key' => '',
    //     'secret' => ''
    // ],

];
PHP;
        file_put_contents($path . $filename, $content);
    }

    private function generateGitignore(string $path): void
    {
        $filename = '.gitignore';
        $content = <<<GITIGNORE
# Config sensible
/Config/database.config.php
/Config/deploy.config.php
/Config/api.config.php

# Storage
/Storage/

# OS
.DS_Store
Thumbs.db
GITIGNORE;

        file_put_contents($path . '/' . $filename, $content);
    }

    private function generateDefaultCss(string $path): void
    {
        $filename = 'app.css';
        $content = <<<CSS
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body  {
    width: 100%;
    height: 100%;
}

body.layout-body {
    font-family: 'Helvetica Neue', Arial, sans-serif;
    color: #2c3e50;
    background-color: #ffffff;
    line-height: 1.6;
    display: flex;
    flex-direction: column;
}

.layout-header {
    background-color: #000000;
    color: #ffffff;
    padding: 10px;
    text-align: center;
}

.layout-header h1 {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.layout-header .subtitle {
    font-size: 1rem;
    color: #cccccc;
}

.layout-header .locales {
    margin-top: 10px;
}

.layout-header a {
    color: #ffffff;
    text-decoration: none;
    margin: 5px;
    padding: 2px 5px;
}

.layout-header a.currentLocale {
    background: #ffffff;
    border-radius: 5px;
    color: #222222;
}

.layout-main {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 2rem;
}

.landing-section {
    text-align: center;
    max-width: 700px;
    background: #fefefe;
    padding: 3rem 2rem;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.landing-section h2 {
    font-size: 1.8rem;
    margin-bottom: 1rem;
    color: #000000;
}

.landing-section .intro {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: #444444;
}

.landing-section .note {
    margin-bottom: 2rem;
    color: #666666;
}

.landing-section .actions {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    justify-content: center;
    align-items: center;
}

.landing-section .action-block p {
    font-weight: bold;
    margin-bottom: 0.3rem;
    color: #2c3e50;
}

.landing-section .actions code {
    display: block;
    background: #f0f0f0;
    color: #2c3e50;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    cursor: default;
}

.landing-section .actions code:hover {
    background: #e0e0e0;
}

.layout-footer {
    background-color: #000000;
    color: #ffffff;
    text-align: center;
    padding: 1rem 2rem;
    font-size: 0.9rem;
}

CSS;

        file_put_contents($path . $filename, $content);
    }

    private function generateDefaultJs(string $path): void
    {
        $filename = 'app.js';
        $content = <<<JS
document.addEventListener('DOMContentLoaded', () => {
    console.log('NeoPHP landing JS chargé');

    // Pour futur : copier les commandes avec click
    const codes = document.querySelectorAll('.landing-section .actions code');
    codes.forEach(code => {
        code.addEventListener('click', () => {
            navigator.clipboard.writeText(code.textContent.trim());
            code.style.backgroundColor = '#d0ffd0';
            setTimeout(() => code.style.backgroundColor = '#f0f0f0', 500);
        });
    });
});
JS;

        file_put_contents($path . $filename, $content);
    }

    private function generateDefaultTranslations($path): void
    {
        $fr_translation = <<<PHP
<?php

return [
    'page_title' => 'Bienvenue sur votre projet',
    'header' => [
        'title' => 'Projet NeoPHP prêt à l\'emploi',
        'sub_title' => 'Votre application est correctement configurée et prête au développement.'
    ],
    'Français' => 'Français',
    'Anglais' => 'Anglais',
    'title' => [
        'message' => 'Bienvenue sur :projectName',
        'install' => 'Félicitations ! Tous vos dossiers et fichiers de base sont correctement créés.',
        'note' => 'Vous pouvez maintenant commencer à développer votre projet NeoPHP.'
    ],
    'command' => [
        'create_crud' => 'Créer un CRUD complet (Controller + vues) :',
        'create_controller' => 'Créer un Controller simple :'
    ]
];
PHP;

        $en_translation = <<<PHP
<?php

return [
    'page_title' => 'Welcome to your project',
    'header' => [
        'title' => 'NeoPHP Project Ready to Use',
        'sub_title' => 'Your application is properly configured and ready for development.'
    ],
    'Français' => 'French',
    'Anglais' => 'English',
    'title' => [
        'message' => 'Welcome to :projectName',
        'install' => 'Congratulations! All your base folders and files have been created successfully.',
        'note' => 'You can now start developing your NeoPHP project.'
    ],
    'command' => [
        'create_crud' => 'Create a full CRUD (Controller + views):',
        'create_controller' => 'Create a simple Controller:'
    ]
];
PHP;

        file_put_contents($path . 'fr/welcome.php', $fr_translation);
        file_put_contents($path . 'en/welcome.php', $en_translation);
    }

    private function pascalCaseSlug(string $string): string
    {
        $string = preg_replace('/[^a-zA-Z0-9]+/', ' ', $string);
        $string = ucwords(strtolower(trim($string)));
        return str_replace(' ', '', $string);
    }

    private function getAvailableHostPort(): array
    {
        $usedPorts = [];

        $srcDir = ROOT_DIR . '/src/';
        foreach (glob($srcDir . '*', GLOB_ONLYDIR) as $projectDir) {
            $configFile = $projectDir . '/Config/app.config.php';
            if (!file_exists($configFile)) continue;

            $config = include $configFile;
            $access = $config['access'] ?? null;
            if ($access && str_contains($access, ':')) {
                [$host, $port] = explode(':', $access);
                $usedPorts[] = (int)$port;
            }
        }

        $host = 'localhost';
        $port = 8000;

        while (in_array($port, $usedPorts)) {
            $port++;
        }

        return [$host, $port];
    }

    private function generateProjectComposer(string $path, string $name): void
    {
        $packageName = strtolower($name) . '/app';
        $content = json_encode([
            'name' => $packageName,
            'description' => "Projet $name - NeoPHP",
            'type' => 'library',
            'minimum-stability' => 'dev',
            'require' => new \stdClass(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        file_put_contents($path . '/composer.json', $content);
        echo "composer.json du projet '$name' créé.\n";
    }

    private function registerInRootComposer(string $name): void
    {
        $rootComposerPath = ROOT_DIR . '/composer.json';
        $packageName = strtolower($name) . '/app';

        if (!file_exists($rootComposerPath)) {
            echo "Attention : composer.json racine introuvable.\n";
            return;
        }

        $rootComposer = json_decode(file_get_contents($rootComposerPath), true);

        $repositories = $rootComposer['repositories'] ?? [];
        $alreadyExists = array_filter(
            $repositories,
            fn($repo) => ($repo['url'] ?? '') === 'src/' . $name
        );

        if (!empty($alreadyExists)) {
            echo "Le projet '$name' est déjà enregistré dans le composer.json racine.\n";
            return;
        }

        $rootComposer['minimum-stability'] = 'dev';
        $rootComposer['prefer-stable'] = true;

        $rootComposer['repositories'][] = [
            'type'    => 'path',
            'url'     => 'src/' . $name,
            'options' => ['symlink' => false],
        ];
        $rootComposer['require'][$packageName] = '@dev';

        file_put_contents(
            $rootComposerPath,
            json_encode($rootComposer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        echo "composer.json racine mis à jour.\n";
    }
}
