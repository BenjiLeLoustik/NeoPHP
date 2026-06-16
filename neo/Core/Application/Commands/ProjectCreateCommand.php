<?php
declare(strict_types=1);

namespace Neo\Core\Application\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

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
            mode: InputArgument::REQUIRED
        );

        $this->addOption(
            name: 'skeleton',
            mode: InputOption::NONE,
            description: 'Create only the base folder structure'
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

    private function generateDirectories(string $path, string $name, string $originalName, string $host, int $port, bool $skeleton): void
    {
        $directories = [
            'App/Controllers/', 'App/Middlewares/', 'App/Services/', 'App/Views/',
            'App/Forms/', 'Assets/', 'Config/', 'Model/', 'Repository/',
            'Storage/', 'Translations',
        ];

        foreach ($directories as $directory) {
            Fs::ensureDir($path . '/' . $directory);
        }

        $this->generateAppConfig($path . '/Config/', $name, $host, $port);
        $this->generateDatabaseConfig($path . '/Config/', $name);
        $this->generateDeployConfig($path . '/Config/', $name);
        $this->generateLoggerConfig($path . '/Config/', $name);
        $this->generateCacheConfig($path . '/Config/', $name);
        $this->generateTwigConfig($path . '/Config/', $name);
        $this->generateSessionConfig($path . '/Config/', $name);
        $this->generateAPIConfig($path . '/Config/', $name);
        $this->generateMailerConfig($path . '/Config/', $name);
        $this->generateGitignore($path);

        if ($skeleton) return;

        $extra = [
            'Translations/fr', 'Translations/en', 'Assets/css', 'Assets/js',
            'App/Views/errors/', 'App/Views/pages/', 'App/Views/layouts/',
            'App/Views/pages/default/', 'App/Views/partials/',
        ];

        foreach ($extra as $directory) {
            Fs::ensureDir($path . '/' . $directory);
        }

        $this->generateDefaultController($path . '/App/Controllers/', $name, $originalName);
        $this->generateDefaultLayoutView($path . '/App/Views/layouts/', $name);
        $this->generateDefaultView($path . '/App/Views/pages/default/', $name);
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

use Neo\Core\Controller\AbstractController;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;
use Neo\Core\Http\Response\Response;
use Neo\Core\Http\Response\RedirectResponse;
use Neo\Core\Translation\TranslationManager;

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
    public function changeLocal(string \$locale, TranslationManager \$tm): RedirectResponse
    {
        \$tm->setLocale(\$locale);
        return \$this->redirectToRoute('default.index');
    }
}
PHP;
        file_put_contents($path . 'DefaultController.php', $content);
    }

    private function generateDefaultLayoutView(string $path, string $name): void
    {
        $content = <<<TWIG
<!DOCTYPE html>
<html lang="fr">
    <head><title>{% block title %}{% endblock %}</title></head>
    <body>
        <main>{% block content %}{% endblock %}</main>
    </body>
</html>
TWIG;
        file_put_contents($path . 'base_layout.html.twig', $content);
    }

    private function generateDefaultView(string $path, string $name): void
    {
        $content = <<<TWIG
{% extends 'layouts/base_layout.html.twig' %}
{% block content %}<h1>{{ projectName }}</h1>{% endblock %}
TWIG;
        file_put_contents($path . 'index.html.twig', $content);
    }

    private function generateAppConfig(string $path, string $name, string $host, int $port): void
    {
        $content = <<<PHP
<?php
declare(strict_types=1);
return ['general' => ['name' => "$name"], 'access' => "$host:$port"];
PHP;
        file_put_contents($path . 'app.config.php', $content);
    }

    private function generateDatabaseConfig(string $path, string $name): void
    {
        file_put_contents($path . 'database.config.php', "<?php\ndeclare(strict_types=1);\nreturn ['enabled' => false];");
    }

    private function generateDeployConfig(string $path, string $name): void
    {
        file_put_contents($path . 'deploy.config.php', "<?php\ndeclare(strict_types=1);\nreturn [];");
    }

    private function generateLoggerConfig(string $path, string $name): void
    {
        file_put_contents($path . 'logger.config.php', "<?php\ndeclare(strict_types=1);\nreturn ['enabled' => false];");
    }

    private function generateCacheConfig(string $path, string $name): void
    {
        file_put_contents($path . 'cache.config.php', "<?php\ndeclare(strict_types=1);\nreturn ['enabled' => true];");
    }

    private function generateTwigConfig(string $path, string $name): void
    {
        file_put_contents($path . 'twig.config.php', "<?php\ndeclare(strict_types=1);\nreturn ['cache' => true];");
    }

    private function generateSessionConfig(string $path, string $name): void
    {
        file_put_contents($path . 'session.config.php', "<?php\ndeclare(strict_types=1);\nreturn ['session' => ['enabled' => true]];");
    }

    private function generateAPIConfig(string $path, string $name): void
    {
        file_put_contents($path . 'api.config.php', "<?php\ndeclare(strict_types=1);\nreturn [];");
    }

    private function generateMailerConfig(string $path, string $name): void
    {
        file_put_contents($path . 'mailer.config.php', "<?php\ndeclare(strict_types=1);\nreturn ['enabled' => false];");
    }

    private function generateGitignore(string $path): void
    {
        file_put_contents($path . '/.gitignore', "/Config/*.config.php\n/Storage/");
    }

    private function generateDefaultCss(string $path): void
    {
        file_put_contents($path . 'app.css', "body { font-family: sans-serif; }");
    }

    private function generateDefaultJs(string $path): void
    {
        file_put_contents($path . 'app.js', "console.log('App ready');");
    }

    private function generateDefaultTranslations(string $path): void
    {
        file_put_contents($path . 'fr/welcome.php', "<?php return [];");
        file_put_contents($path . 'en/welcome.php', "<?php return [];");
    }

    private function generateProjectComposer(string $path, string $name): void
    {
        $content = json_encode(['name' => strtolower($name) . '/app', 'type' => 'library', 'require' => new \stdClass()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($path . '/composer.json', $content);
    }

    private function registerInRootComposer(string $name): void
    {
        $rootPath = ROOT_DIR . '/composer.json';
        if (!file_exists($rootPath)) return;
        $data = json_decode(file_get_contents($rootPath), true);
        $data['repositories'][] = ['type' => 'path', 'url' => 'src/' . $name, 'options' => ['symlink' => false]];
        $data['require'][strtolower($name) . '/app'] = '@dev';
        file_put_contents($rootPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    private function getAvailableHostPort(): array
    {
        $used = [];
        foreach (glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR) as $dir) {
            if (file_exists($cf = $dir . '/Config/app.config.php')) {
                $c = include $cf;
                if (($a = $c['access'] ?? null) && str_contains($a, ':')) $used[] = (int) explode(':', $a)[1];
            }
        }
        $port = 8000;
        while (in_array($port, $used)) $port++;
        return ['localhost', $port];
    }
}