<?php
declare(strict_types=1);

namespace Neo\Core\Application\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\Utils\Config\Templates\ApiConfigTemplate;
use Neo\Core\Utils\Config\Templates\AppConfigTemplate;
use Neo\Core\Utils\Config\Templates\AuthConfigTemplate;
use Neo\Core\Utils\Config\Templates\CacheConfigTemplate;
use Neo\Core\Utils\Config\Templates\DatabaseConfigTemplate;
use Neo\Core\Utils\Config\Templates\LoggerConfigTemplate;
use Neo\Core\Utils\Config\Templates\SessionConfigTemplate;
use Neo\Core\Utils\Config\Templates\TwigConfigTemplate;
use Neo\Core\Utils\Config\Writer\ConfigTemplateWriter;

#[Command(
    name: 'project:create',
    description: 'Create a new NeoPHP project inside ./src/',
    category: 'Project',
)]
final class ProjectCreateCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'projectName',
            description: 'Name of the project to create',
            mode: InputArgument::REQUIRED,
        );

        $this->addOption(
            name: 'skeleton',
            mode: InputOption::NONE,
            description: 'Create only the base folder structure',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $originalName = $input->getArgument('projectName');
        $skeleton = (bool) $input->getOption('skeleton');

        $name = Fs::pascalCase($originalName);
        $path = ROOT_DIR . "/src/$name";

        if (is_dir($path)) {
            Output::error("Project '$name' already exists.");
            return ExitCode::FAILURE;
        }

        [$host, $port] = $this->getAvailableHostPort();

        Fs::ensureDir($path);
        $this->generateDirectories($path, $name, $originalName, $host, $port, $skeleton);
        $this->generateProjectComposer($path, $name);
        $this->registerInRootComposer($name);

        Output::success("Project created in ./src/$name");
        Output::info("Server: $host:$port");
        Output::info('Running composer update…');

        $cmdOutput = shell_exec('composer update 2>&1');
        echo $cmdOutput . "\n";

        Output::success('Composer update done.');

        return ExitCode::SUCCESS;
    }

    private function generateDirectories(
        string $path,
        string $name,
        string $originalName,
        string $host,
        int $port,
        bool $skeleton,
    ): void {
        $directories = [
            'App/Controllers/',
            'App/Middlewares/',
            'App/Services/',
            'Templates/',
            'Assets/',
            'Config/',
            'Database/Migrations/',
            'Database/Model/',
            'Database/Repository/',
            'Database/Forms/',
            'Storage/',
            'Translations',
        ];

        foreach ($directories as $directory) {
            Fs::ensureDir($path . '/' . $directory);
        }

        ConfigTemplateWriter::write(
            templates: [
                new AppConfigTemplate(),
                new DatabaseConfigTemplate(),
                new LoggerConfigTemplate(),
                new CacheConfigTemplate(),
                new TwigConfigTemplate(),
                new SessionConfigTemplate(),
                new ApiConfigTemplate(),
                new AuthConfigTemplate(),
            ],
            configPath: $path . '/Config/',
            projectName: $name,
            context: ['host' => $host, 'port' => $port],
            askOverwrite: false,
        );

        $this->generateGitignore($path);
        $this->generateDefaultModule($path, $name);

        if ($skeleton) {
            return;
        }

        $extra = [
            'Translations/fr',
            'Translations/en',
            'Assets/css',
            'Assets/js',
            'Templates/errors/',
            'Templates/pages/',
            'Templates/layouts/',
            'Templates/pages/default/',
            'Templates/partials/',
        ];

        foreach ($extra as $directory) {
            Fs::ensureDir($path . '/' . $directory);
        }

        $this->generateDefaultController($path . '/App/Controllers/', $name, $originalName);
        $this->generateDefaultLayoutView($path . '/Templates/layouts/', $name);
        $this->generateDefaultView($path . '/Templates/pages/default/', $name);
        $this->generateDefaultCss($path . '/Assets/css/');
        $this->generateDefaultJs($path . '/Assets/js/');
        $this->generateDefaultTranslations($path . '/Translations/');
    }

    private function generateDefaultController(string $path, string $name, string $originalName): void
    {
        $content = <<<PHP
<?php
declare(strict_types=1);

namespace Neo\Src\\$name\App\Controllers;

use Neo\Core\Controller\AbstractController;use Neo\Core\Http\Response\Types\RedirectResponse;use Neo\Core\Http\Response\Types\Response;use Neo\Core\Routing\Attribute\MainRoute;use Neo\Core\Routing\Attribute\Route;use Neo\Core\Translation\TranslationManager;

#[MainRoute(path: '/', name: 'default')]
final class DefaultController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return \$this->render('pages/default/index.html.twig', [
            'projectName' => "$name",
            'projectPath' => "$path",
        ]);
    }

    #[Route(path: '/change-locale/{locale}', name: 'change.locale', methods: ['GET'])]
    public function changeLocal(
        string \$locale,
        TranslationManager \$translationManager
    ): RedirectResponse {
        \$translationManager->setLocale(\$locale);
        return \$this->redirectToRoute('default.index');
    }
}
PHP;

        file_put_contents($path . 'DefaultController.php', $content);
    }

    private function generateDefaultLayoutView(string $path, string $name): void
    {
        $content = <<<'TWIG'
{# ./src/__NAME__/Templates/layouts/base_layout.html.twig #}

<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{% block title %}{% endblock %}</title>
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        {% block stylesheets %}{% endblock %}
    </head>
    <body class="layout-body">
        <header class="layout-header">
            {% block header %}
                <h1>{{ translate('NeoPHP Project Ready to Use', domain='header') }}</h1>
                <p class="subtitle">{{ translate('Your application is properly configured and ready for development.', domain='header') }}</p>
                <div class="locales">
                    {% for locale, label in getLocales() %}
                        <a class="{% if locale == getLocale() %}currentLocale{% endif %}"
                           href="{{ path('default.change.locale', {'locale': locale}) }}">
                            {{ label }}
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
                <p>{{ translate('© :year - NeoPHP', {'year': "now"|date("Y")}, domain='footer') }}</p>
            {% endblock %}
        </footer>

        <script src="{{ asset('js/app.js') }}"></script>
        {% block javascripts %}{% endblock %}
    </body>
</html>
TWIG;

        $content = str_replace('__NAME__', $name, $content);

        file_put_contents($path . 'base_layout.html.twig', $content);
    }

    private function generateDefaultView(string $path, string $name): void
    {
        $content = <<<'TWIG'
{# ./src/__NAME__/Templates/pages/default/index.html.twig #}

{% extends 'layouts/base_layout.html.twig' %}

{% block title %}{{ translate('Welcome to your project') }}{% endblock %}

{% block content %}
    <section class="landing-section">
        <h2>{{ translate('Welcome to :projectName', {'projectName': projectName}) }}</h2>
        <p class="intro">{{ translate('Congratulations! All your base folders and files have been created successfully.') }}</p>
        <p class="note">{{ translate('You can now start developing your NeoPHP project.') }}</p>

        <div class="actions">
            <div class="action-block">
                <p>{{ translate('Create a full CRUD (Controller + views):') }}</p>
                <code>php bin/neo make:crud &lt;Entity&gt; --project={{ app.name }}</code>
            </div>
            <div class="action-block">
                <p>{{ translate('Create a simple Controller:') }}</p>
                <code>php bin/neo make:controller &lt;ControllerName&gt; --project={{ app.name }}</code>
            </div>
        </div>
    </section>
{% endblock %}
TWIG;

        $content = str_replace('__NAME__', $name, $content);

        file_put_contents($path . 'index.html.twig', $content);
    }

    private function generateGitignore(string $path): void
    {
        $content = <<<GITIGNORE
# Sensitive config
/Config/database.config.php
/Config/api.config.php
/Config/auth.config.php

# Storage
/Storage/

# OS
.DS_Store
Thumbs.db
GITIGNORE;

        file_put_contents($path . '/.gitignore', $content);
    }

    private function generateDefaultCss(string $path): void
    {
        $content = <<<CSS
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { width: 100%; height: 100%; }

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
.layout-header h1        { font-size: 2rem; margin-bottom: .5rem; }
.layout-header .subtitle { font-size: 1rem; color: #ccc; }
.layout-header .locales  { margin-top: 10px; }
.layout-header a { color: #fff; text-decoration: none; margin: 5px; padding: 2px 5px; }
.layout-header a.currentLocale { background: #fff; border-radius: 5px; color: #222; }

.layout-main { flex: 1; display: flex; justify-content: center; align-items: center; padding: 2rem; }

.landing-section {
    text-align: center;
    max-width: 700px;
    background: #fefefe;
    padding: 3rem 2rem;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,.1);
}
.landing-section h2     { font-size: 1.8rem; margin-bottom: 1rem; color: #000; }
.landing-section .intro { font-size: 1.1rem; margin-bottom: .5rem; color: #444; }
.landing-section .note  { margin-bottom: 2rem; color: #666; }
.landing-section .actions { display: flex; flex-direction: column; gap: 1.5rem; align-items: center; }
.landing-section .action-block p { font-weight: bold; margin-bottom: .3rem; color: #2c3e50; }
.landing-section .actions code {
    display: block;
    background: #f0f0f0;
    color: #2c3e50;
    padding: .5rem 1rem;
    border-radius: 6px;
    font-family: 'Courier New', Courier, monospace;
    font-size: .95rem;
    cursor: default;
    transition: background .2s;
}
.landing-section .actions code:hover { background: #e0e0e0; }

.layout-footer { background-color: #000; color: #fff; text-align: center; padding: 1rem 2rem; font-size: .9rem; }
CSS;

        file_put_contents($path . 'app.css', $content);
    }

    private function generateDefaultJs(string $path): void
    {
        $content = <<<JS
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.landing-section .actions code').forEach(code => {
        code.addEventListener('click', () => {
            navigator.clipboard.writeText(code.textContent.trim());
            code.style.backgroundColor = '#d0ffd0';
            setTimeout(() => code.style.backgroundColor = '#f0f0f0', 500);
        });
    });
});
JS;

        file_put_contents($path . 'app.js', $content);
    }

    private function generateDefaultTranslations(string $path): void
    {
        $domains = [
            'common' => [
                'fr' => [
                    'Welcome to your project' => 'Bienvenue sur votre projet',
                    'Welcome to :projectName' => 'Bienvenue sur :projectName',
                    'Congratulations! All your base folders and files have been created successfully.' => 'Félicitations ! Tous vos dossiers et fichiers de base sont correctement créés.',
                    'You can now start developing your NeoPHP project.' => 'Vous pouvez maintenant commencer à développer votre projet NeoPHP.',
                    'Create a full CRUD (Controller + views):' => 'Créer un CRUD complet (Controller + vues) :',
                    'Create a simple Controller:' => 'Créer un Controller simple :',
                ],
                'en' => [
                    'Welcome to your project' => 'Welcome to your project',
                    'Welcome to :projectName' => 'Welcome to :projectName',
                    'Congratulations! All your base folders and files have been created successfully.' => 'Congratulations! All your base folders and files have been created successfully.',
                    'You can now start developing your NeoPHP project.' => 'You can now start developing your NeoPHP project.',
                    'Create a full CRUD (Controller + views):' => 'Create a full CRUD (Controller + views):',
                    'Create a simple Controller:' => 'Create a simple Controller:',
                ],
            ],
            'header' => [
                'fr' => [
                    'NeoPHP Project Ready to Use' => "Projet NeoPHP prêt à l'emploi",
                    'Your application is properly configured and ready for development.' => 'Votre application est correctement configurée et prête au développement.',
                ],
                'en' => [
                    'NeoPHP Project Ready to Use' => 'NeoPHP Project Ready to Use',
                    'Your application is properly configured and ready for development.' => 'Your application is properly configured and ready for development.',
                ],
            ],
            'footer' => [
                'fr' => [
                    '© :year - NeoPHP' => '© :year - NeoPHP',
                ],
                'en' => [
                    '© :year - NeoPHP' => '© :year - NeoPHP',
                ],
            ],
        ];

        foreach ($domains as $domain => $locales) {
            foreach ($locales as $locale => $translations) {
                $lines = [];
                foreach ($translations as $key => $value) {
                    $lines[] = '    ' . var_export($key, true) . ' => ' . var_export($value, true);
                }

                $fileContent = "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n" . implode(",\n", $lines) . "\n];\n";

                Fs::ensureDir($path . $locale);
                file_put_contents($path . "$locale/$domain.php", $fileContent);
            }
        }
    }

    private function generateProjectComposer(string $path, string $name): void
    {
        $data = [
            'name' => strtolower($name) . '/app',
            'description' => "Project $name - NeoPHP",
            'type' => 'library',
            'minimum-stability'  => 'dev',
            'require' => new \stdClass(),
        ];

        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        file_put_contents($path . '/composer.json', $content);
        Output::muted("composer.json created for project '$name'.");
    }

    private function registerInRootComposer(string $name): void
    {
        $rootPath = ROOT_DIR . '/composer.json';
        $packageName = strtolower($name) . '/app';

        if (!file_exists($rootPath)) {
            Output::warning('Root composer.json not found.');
            return;
        }

        $data = json_decode(file_get_contents($rootPath), true);
        $repositories = $data['repositories'] ?? [];

        $alreadyExists = array_filter(
            $repositories,
            fn($repo) => ($repo['url'] ?? '') === 'src/' . $name,
        );

        if (!empty($alreadyExists)) {
            Output::skip("Project '$name' already registered in root composer.json.");
            return;
        }

        $data['minimum-stability'] = 'dev';
        $data['prefer-stable'] = true;
        $data['repositories'][] = [
            'type' => 'path',
            'url' => 'src/' . $name,
            'options' => ['symlink' => false],
        ];
        $data['require'][$packageName] = '@dev';

        file_put_contents(
            $rootPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        Output::muted('Root composer.json updated.');
    }

    /**
     * @return array{string, int}
     */
    private function getAvailableHostPort(): array
    {
        $used = [];

        foreach (glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR) as $dir) {
            $configPath = $dir . '/Config/app.config.php';

            if (!file_exists($configPath)) {
                continue;
            }

            $config = include $configPath;
            $access = $config['access'] ?? null;

            if ($access && str_contains($access, ':')) {
                $used[] = (int) explode(':', $access)[1];
            }
        }

        $port = 8000;
        while (in_array($port, $used)) {
            $port++;
        }

        return ['localhost', $port];
    }

    private function generateDefaultModule(string $path, string $name): void
    {
        $content = <<<PHP
<?php
declare(strict_types=1);

namespace Neo\Src\\$name;

use Neo\Core\DI\Container;
use Neo\Core\Module\Interface\ModuleInterface;

final class {$name}Module implements ModuleInterface
{
    public function dependencies(): array
    {
        return [
            // ClassModule::class,
        ];
    }

    public function register(Container \$container): void
    {
        // Registration of project-specific services ($name)
    }

    public function init(Container \$container): object
    {
        return \$this;
    }
}
PHP;

        file_put_contents($path . "/{$name}Module.php", $content);
    }
}