<?php
declare(strict_types=1);

namespace Neo;

use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\DI\Container;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Event\Event\RequestEvent;
use Neo\Core\Event\Event\ResponseEvent;
use Neo\Core\Event\EventDispatcher;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Http\Request;
use Neo\Core\Http\Response\Response;
use Neo\Core\Module\ModuleManager;
use Neo\Core\Routing\Router;
use Neo\Core\Utils\Config\Config;

class App
{
    private Container $container;

    public function __construct()
    {
        $this->container = new Container();
        $this->container->set(Container::class, fn() => $this->container);

        if (php_sapi_name() === 'cli') {
            $this->container->set(Request::class, fn() => Request::createEmpty());
        } else {
            $this->container->set(Request::class, fn() => Request::fromGlobals());
        }

        $this->getCurrentApplication();
        $this->registerBasePaths();

        (new ModuleManager($this->container))
            ->discover(__DIR__ . '/Core')
            ->boot();

        if (php_sapi_name() !== 'cli') {
            $this->container->get(Request::class)
                ->enablePreviousUrlTracking(
                    $this->container->get(Session::class)
                );
        }

        date_default_timezone_set(
            $this->container->get(Config::class)->from('app')->get('date.timezone')
        );
    }

    private function registerBasePaths(): void
    {
        $appName = $this->container->get('application');
        $basePath = realpath(__DIR__ . '/../');

        $this->container->set('basePath', $basePath);

        if (is_dir($basePath . '/public_html')) {
            $publicPath = realpath($basePath . '/public_html');
        } elseif (is_dir($basePath . '/public')) {
            $publicPath = realpath($basePath . '/public');
        } else {
            $publicPath = $basePath . '/public';
        }

        $this->container->set('publicPath', $publicPath);
        $this->container->set('buildsPath', $publicPath . '/builds/');
        $this->container->set('srcPath', $basePath . '/src');
        $this->container->set('storagePath', $basePath . '/src/' . $appName . '/Storage');
        $this->container->set('configsPath', $basePath . '/src/' . $appName . '/Config');
        $this->container->set('viewsPath', $basePath . '/src/' . $appName . '/App/Views');
        $this->container->set('controllersPath', $basePath . '/src/' . $appName . '/App/Controllers');
        $this->container->set('assetsPath', $basePath . '/src/' . $appName . '/Assets/');
        $this->container->set('repositoryPath', $basePath . '/src/' . $appName . '/Repository');
        $this->container->set('modelPath', $basePath . '/src/' . $appName . '/Model');
        $this->container->set('formPath', $basePath . '/src/' . $appName . '/App/Forms');
        $this->container->set('listenersPath', $basePath . '/src/' . $appName . '/App/Event/Listener');
        $this->container->set('cronsPath', $basePath . '/src/' . $appName . '/App/Crons');
        $this->container->set('manifestFilename', 'manifest.json');
        $this->container->set('controllerNamespace', 'Neo\\Src\\' . $appName . '\\App\\Controllers\\');
        $this->container->set('modelNamespace', 'Neo\\Src\\' . $appName . '\\Model');
        $this->container->set('repositoryNamespace', 'Neo\\Src\\' . $appName . '\\Repository');
        $this->container->set('formNamespace', 'Neo\\Src\\' . $appName . '\\App\\Forms');

        if (!empty($GLOBALS['_NEO_TEST_CONFIGS_PATH'])) {
            $this->container->set('testConfigsPath', $GLOBALS['_NEO_TEST_CONFIGS_PATH']);
        }
    }

    public function run(): Response
    {
        AbstractModel::clearIdentityMap();

        $request = $this->container->get(Request::class);
        $response = $this->container->get(Response::class);
        $router = $this->container->get(Router::class);
        $dispatcher = $this->container->get(EventDispatcher::class);

        $dispatcher->dispatch(new RequestEvent($request));

        $result = $router->dispatch($request, $response);

        if ($result instanceof Response) {
            $responseEvent = new ResponseEvent($result);
            $dispatcher->dispatch($responseEvent);
            return $responseEvent->getResponse();
        }

        if (is_string($result) || is_numeric($result)) {
            $response->setContent((string) $result);
        } elseif (is_array($result) || is_object($result)) {
            $response->setHeader('Content-Type', 'application/json');
            $response->setContent(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        } else {
            $response->setStatusCode(204);
        }

        $responseEvent = new ResponseEvent($response);
        $dispatcher->dispatch($responseEvent);
        return $responseEvent->getResponse();
    }

    private function getCurrentApplication(): void
    {
        if (php_sapi_name() === 'cli') {
            if (!empty($GLOBALS['_NEO_TEST_PROJECT'])) {
                $this->container->set('application', $GLOBALS['_NEO_TEST_PROJECT']);
                return;
            }

            global $argv;
            $project = null;

            foreach ($argv as $arg) {
                if (str_starts_with($arg, '--project=')) {
                    $project = substr($arg, strlen('--project='));
                    break;
                }
            }

            if (!$project) {
                throw new FrameworkException(
                    title: 'Application Error',
                    message: "You must pass --project=ProjectName in CLI.",
                    code: 500
                );
            }

            $this->container->set('application', $project);
            return;
        }

        $request = $this->container->get(Request::class);
        $serverData = $request->server() ?? $_SERVER;

        $serverName = $serverData['SERVER_NAME'] ?? $serverData['HTTP_HOST'] ?? null;
        $serverPort = (string) ($serverData['SERVER_PORT'] ?? '');

        if (!$serverName) {
            throw new FrameworkException(
                title: 'Application Error',
                message: "Unable to detect the server name.",
                code: 500
            );
        }

        $server = $serverName;
        if (!empty($serverPort) && !in_array($serverPort, ['80', '443'])) {
            $server .= ':' . $serverPort;
        }

        foreach (glob(__DIR__ . '/../src/*/Config/app.config.php') as $file) {
            $config       = require $file;
            $accessServer = $config['access'] ?? null;

            if ($accessServer === $server || $accessServer === $serverName) {
                $this->container->set('application', basename(dirname($file, 2)));
                return;
            }
        }

        $projects = glob(__DIR__ . '/../src/*', GLOB_ONLYDIR);
        if (count($projects) === 1) {
            $this->container->set('application', basename($projects[0]));
            return;
        }

        throw new FrameworkException(
            title: 'Application Error',
            message: sprintf("No application detected for access: '%s'.", $server),
            code: 500
        );
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}