<?php
declare(strict_types=1);

namespace Neo\Core\View;

use Neo\Core\DI\Container;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Utils\Config;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Extension\DebugExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Twig\TwigFilter;

class View
{
    protected Container $container;
    private Environment $twig;

    public function __construct(Container $container)
    {
        $this->container = $container;

        $config     = $this->container->get(Config::class);
        $twigConfig = $config->from('twig')->all();

        $loader = new FilesystemLoader($this->container->get('viewsPath'));

        $twigCache = $twigConfig['cache']
            ? $this->container->get('storagePath') . '/var/cache/Twig/'
            : false;

        $timezone = $config->from('app')->get('date.timezone') ?? 'UTC';
        date_default_timezone_set($timezone);

        $this->twig = new Environment($loader, [
            'timezone' => $timezone,
            'cache' => $twigCache,
            'debug' => $twigConfig['debug'] ?? false,
            'auto_reload' => $twigConfig['auto_reload'] ?? true,
            'autoescape' => $twigConfig['auto_escape'] ?? 'html',
            'charset' => $twigConfig['charset'] ?? 'UTF-8',
            'strict_variables' => $twigConfig['strict_variables'] ?? false,
            ...($twigConfig['options'] ?? [])
        ]);

        $this->twig->getExtension(CoreExtension::class)->setTimezone($timezone);

        if ($twigConfig['debug']) {
            $this->twig->addExtension(new DebugExtension());
        }

        $this->twig->addExtension(new IntlExtension());

        $appConfig = $config->from('app')->get('general');
        $this->twig->addGlobal('app', $appConfig);
    }

    public function render(string $template, array $params = []): string
    {
        try {
            return $this->twig->render($template, $params);
        } catch (\Twig\Error\LoaderError $e) {
            throw new FrameworkException(
                title: 'Template Not Found',
                message: "Le template '{$template}' est introuvable : " . $e->getMessage(),
                code: 404,
                previous: $e
            );
        } catch (\Twig\Error\SyntaxError $e) {
            throw new FrameworkException(
                title: 'Template Syntax Error',
                message: "Erreur de syntaxe dans le template '{$template}' : " . $e->getMessage(),
                code: 500,
                previous: $e
            );
        } catch (\Twig\Error\RuntimeError $e) {
            throw new FrameworkException(
                title: 'Template Runtime Error',
                message: "Erreur d'exécution dans le template '{$template}' : " . $e->getMessage(),
                code: 500,
                previous: $e
            );
        }
    }

    public function renderIfExists(string $template, array $params = []): ?string
    {
        try {
            return $this->twig->render($template, $params);
        } catch (\Twig\Error\LoaderError $e) {
            return null;
        }
    }

    public function getTwig(): Environment
    {
        return $this->twig;
    }

    public function registerTwigFunction(string $name, callable $callable, array $options = []): void
    {
        $this->twig->addFunction(new TwigFunction($name, $callable, $options));
    }

    public function registerTwigFilter(string $name, callable $callable, array $options = []): void
    {
        $this->twig->addFilter(new TwigFilter($name, $callable, $options));
    }
}