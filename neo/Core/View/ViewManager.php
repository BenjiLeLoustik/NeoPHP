<?php
declare(strict_types=1);

namespace Neo\Core\View;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Package\Interface\PackageInterface;
use Neo\Core\View\Exception\ViewException;
use Neo\Core\View\Interface\TwigExtensionInterface;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Extension\DebugExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

class ViewManager
{
    protected Container $container;
    private Environment $twig;

    /** @var list<array{template: string, params: list<string>, duration: float, error: string|null}> */
    private static array $renders = [];

    /**
     * @throws ContainerException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $config = $this->container->get('view.configModule');
        $twigConfig = $config->from('twig')->all();

        $loader = new FilesystemLoader($this->container->get('viewsPath'));

        if ($this->container->has('packages')) {
            /** @var array<int, PackageInterface> $packages */
            $packages = $this->container->get('packages');

            foreach ($packages as $package) {
                $path = $package->getViewPath();
                if ($path !== null) {
                    $loader->addPath($path, $package->getName());
                }
            }
        }

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

    /**
     * @param array<string, mixed> $params
     * @throws ViewException
     */
    public function render(string $template, array $params = []): string
    {
        $start = microtime(true);

        try {
            $result = $this->twig->render($template, $params);
            $this->recordRender($template, $params, $start, null);
            return $result;
        } catch (\Twig\Error\LoaderError $e) {
            $this->recordRender($template, $params, $start, $e->getMessage());
            throw new ViewException(
                title: 'Template Not Found',
                message: sprintf("Template '%s' not found: %s", $template, $e->getMessage()),
                code: 404,
                previous: $e
            );
        } catch (\Twig\Error\SyntaxError $e) {
            $this->recordRender($template, $params, $start, $e->getMessage());
            throw new ViewException(
                title: 'Template Syntax Error',
                message: sprintf("Syntax error in template '%s': %s", $template, $e->getMessage()),
                code: 500,
                previous: $e
            );
        } catch (\Twig\Error\RuntimeError $e) {
            $this->recordRender($template, $params, $start, $e->getMessage());
            throw new ViewException(
                title: 'Template Runtime Error',
                message: sprintf("Runtime error in template '%s': %s", $template, $e->getMessage()),
                code: 500,
                previous: $e
            );
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function renderIfExists(string $template, array $params = []): ?string
    {
        $start = microtime(true);

        try {
            $result = $this->twig->render($template, $params);
            $this->recordRender($template, $params, $start, null);
            return $result;
        } catch (\Twig\Error\LoaderError $e) {
            $this->recordRender($template, $params, $start, $e->getMessage());
            return null;
        }
    }

    public function getTwig(): Environment
    {
        return $this->twig;
    }

    public function addExtension(TwigExtensionInterface $extension): void
    {
        foreach ($extension->getFunctions() as $name => $definition) {
            if (is_callable($definition)) {
                $this->twig->addFunction(new TwigFunction($name, $definition));
            } else {
                $this->twig->addFunction(
                    new TwigFunction($name, $definition['callable'], $definition['options'] ?? [])
                );
            }
        }

        foreach ($extension->getFilters() as $name => $definition) {
            if (is_callable($definition)) {
                $this->twig->addFilter(new TwigFilter($name, $definition));
            } else {
                $this->twig->addFilter(
                    new TwigFilter($name, $definition['callable'], $definition['options'] ?? [])
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function recordRender(string $template, array $params, float $start, ?string $error): void
    {
        self::$renders[] = [
            'template' => $template,
            'params' => array_keys($params),
            'duration' => round((microtime(true) - $start) * 1000, 2),
            'error' => $error,
        ];
    }

    /**
     * @return list<array{template: string, params: list<string>, duration: float, error: string|null}>
     */
    public static function getRenders(): array
    {
        return self::$renders;
    }
}