<?php
declare(strict_types=1);

namespace Neo;

use Neo\Core\Asset\AssetHandler;
use Neo\Core\Database\DatabaseConnection;
use Neo\Core\Database\Form\FormExtension;
use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\DI\Container;
use Neo\Core\Error\ErrorHandler;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Event\Event\RequestEvent;
use Neo\Core\Event\Event\ResponseEvent;
use Neo\Core\Event\EventDispatcher;
use Neo\Core\Http\Client\Cookie;
use Neo\Core\Http\Client\Flash;
use Neo\Core\Http\Client\Session;
use Neo\Core\Http\File\Uploader;
use Neo\Core\Http\Request;
use Neo\Core\Http\Response\Response;
use Neo\Core\Profiler\Collector\AuthCollector;
use Neo\Core\Profiler\Collector\EventCollector;
use Neo\Core\Profiler\Collector\LogCollector;
use Neo\Core\Profiler\Collector\QueryCollector;
use Neo\Core\Profiler\Collector\RequestCollector;
use Neo\Core\Profiler\Collector\RouterCollector;
use Neo\Core\Profiler\Profiler;
use Neo\Core\Profiler\ProfilerResponseListener;
use Neo\Core\Profiler\Toolbar\Toolbar;
use Neo\Core\Routing\Router;
use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\Security\Middleware\MiddlewareHandler;
use Neo\Core\Security\PasswordManager;
use Neo\Core\Security\Csrf\CsrfTokenManager;
use Neo\Core\Translation\TranslationManager;
use Neo\Core\Translation\TranslationRegistry;
use Neo\Core\Translation\TranslationTwigExtension;
use Neo\Core\Utils\Cache;
use Neo\Core\Utils\Config;
use Neo\Core\Utils\Extension\StringExtension;
use Neo\Core\Utils\Logger;
use Neo\Core\View\View;

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
        $this->registerBaseContainer();

        $this->container->set(Config::class, fn(Container $container) => new Config($container));
        $this->container->set(Logger::class, fn(Container $container) => new Logger($container));
        $this->container->set(Cache::class, fn(Container $container) => new Cache($container));

        $this->registerErrorHandler();

        try {
            $env = $this->container->get(Config::class)->from('app')->get('environment') ?? 'prod';
            $this->container->get(ErrorHandler::class)->setEnv($env);
        } catch (\Throwable) {}

        $this->registerCoreServices();
        $this->registerClientServices();
        $this->registerTranslationServices();

        $request = $this->container->get(Request::class);

        if (php_sapi_name() !== 'cli') {
            $request->enablePreviousUrlTracking($this->container->get(Session::class));
        }

        date_default_timezone_set($this->container->get(Config::class)->from('app')->get('date.timezone'));
    }

    private function registerBaseContainer(): void
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

        $this->container->set('publicPath',          $publicPath);
        $this->container->set('buildsPath',          $publicPath . '/builds/');
        $this->container->set('srcPath',             $basePath . '/src');
        $this->container->set('storagePath',         $basePath . '/src/' . $appName . '/Storage');
        $this->container->set('configsPath',         $basePath . '/src/' . $appName . '/Config');
        $this->container->set('viewsPath',           $basePath . '/src/' . $appName . '/App/Views');
        $this->container->set('controllersPath',     $basePath . '/src/' . $appName . '/App/Controllers');
        $this->container->set('assetsPath',          $basePath . '/src/' . $appName . '/Assets/');
        $this->container->set('repositoryPath',      $basePath . '/src/' . $appName . '/Repository');
        $this->container->set('modelPath',           $basePath . '/src/' . $appName . '/Model');
        $this->container->set('formPath',            $basePath . '/src/' . $appName . '/App/Forms');
        $this->container->set('listenersPath',       $basePath . '/src/' . $appName . '/App/Event/Listener');

        $this->container->set('manifestFilename',    'manifest.json');
        $this->container->set('controllerNamespace', 'Neo\\Src\\' . $appName . '\\App\\Controllers\\');
        $this->container->set('modelNamespace',      'Neo\\Src\\' . $appName . '\\Model');
        $this->container->set('repositoryNamespace', 'Neo\\Src\\' . $appName . '\\Repository');
        $this->container->set('formNamespace',       'Neo\\Src\\' . $appName . '\\App\\Forms');

        if (!empty($GLOBALS['_NEO_TEST_CONFIGS_PATH'])) {
            $this->container->set('testConfigsPath', $GLOBALS['_NEO_TEST_CONFIGS_PATH']);
        }
    }

    private function registerCoreServices(): void
    {
        $this->container->set(View::class, fn(Container $c) => new View($c));
        $this->container->set(Response::class, fn() => new Response());
        $this->container->set(Router::class, fn(Container $c) => new Router($c));

        $this->container->set(AssetHandler::class, fn(Container $c) => new AssetHandler($c));
        $this->container->get(AssetHandler::class);

        $this->container->set(DatabaseConnection::class, fn(Container $c) => new DatabaseConnection($c));
        $this->container->get(DatabaseConnection::class);

        FormExtension::register($this->container);

        $this->container->set(MiddlewareHandler::class, fn(Container $c) => new MiddlewareHandler($c));
        $this->container->set(StringExtension::class, fn(Container $c) => new StringExtension($c));
        $this->container->get(StringExtension::class);

        $this->container->set(AuthManager::class, fn(Container $c) => new AuthManager($c));
        $this->container->set(CsrfTokenManager::class, fn() => new CsrfTokenManager());

        $auth = $this->container->get(AuthManager::class);
        $csrf = $this->container->get(CsrfTokenManager::class);
        $view = $this->container->get(View::class);

        $this->container->set(Uploader::class, fn(Container $c) => new Uploader($c));

        $view->registerTwigFunction('auth_check', fn() => $auth->check());
        $view->registerTwigFunction('auth_user', fn() => $auth->user());
        $view->registerTwigFunction('auth_has_role', fn(string $role) => $auth->hasRole($role));
        $view->registerTwigFunction('csrf_token', fn(string $id = 'default') => $csrf->generateToken($id)->getValue());

        $this->container->set(PasswordManager::class, fn(Container $c) => new PasswordManager($c));

        $this->container->set(EventDispatcher::class, fn(Container $c) => new EventDispatcher($c));
        $this->container->get(EventDispatcher::class);
    }

    private function registerClientServices(): void
    {
        $this->container->set(Session::class, fn() => new Session($this->container));
        $this->container->set(Cookie::class, fn() => new Cookie($this->container));
        $this->container->set(Flash::class, fn() => new Flash($this->container));
    }

    private function registerErrorHandler(): void
    {
        $this->container->set(ErrorHandler::class, fn(Container $c) => new ErrorHandler($c));
        $errorHandler = $this->container->get(ErrorHandler::class);

        if (empty($GLOBALS['_NEO_TEST_PROJECT'])) {
            $errorHandler->register();
        }
    }

    public function run(): Response
    {
        AbstractModel::clearIdentityMap();

        $request = $this->container->get(Request::class);
        $response = $this->container->get(Response::class);
        $router = $this->container->get(Router::class);
        $dispatcher = $this->container->get(EventDispatcher::class);

        $env = $this->container->get(Config::class)->from('app')->get('environment') ?? 'prod';

        if ($env === 'dev' && php_sapi_name() !== 'cli') {
            if (!defined('NEO_PROFILER_ENABLED')) {
                define('NEO_PROFILER_ENABLED', true);
            }

            $profiler = Profiler::getInstance();
            $profiler->addCollector(new RequestCollector($request));
            $profiler->addCollector(new RouterCollector($router));
            $profiler->addCollector(new QueryCollector());
            $profiler->addCollector(new EventCollector($dispatcher));
            $profiler->addCollector(new LogCollector());
            $profiler->addCollector(new AuthCollector($this->container->get(AuthManager::class)));

            $toolbar = new Toolbar($profiler);
            $listener = new ProfilerResponseListener($toolbar);
            $dispatcher->addListenerInstance(ResponseEvent::class, $listener, 'onResponse');
        }

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
                    message: 'Vous devez passer --project=NomDuProjet en CLI.',
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
                message: 'Impossible de détecter le nom du serveur.',
                code: 500
            );
        }

        $server = $serverName;
        if (!empty($serverPort) && !in_array($serverPort, ['80', '443'])) {
            $server .= ':' . $serverPort;
        }

        foreach (glob(__DIR__ . '/../src/*/Config/app.config.php') as $file) {
            $config = require $file;
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
            message: "Aucune application détectée pour l'accès : {$server}.",
            code: 500
        );
    }

    private function registerTranslationServices(): void
    {
        $appName = $this->container->get('application');

        TranslationRegistry::registerPath(
            $this->container->get('srcPath') . '/' . $appName . '/Translations'
        );

        $this->container->set(TranslationManager::class, fn() => new TranslationManager($this->container));

        if ($this->container->get(View::class)) {
            $view = $this->container->get(View::class);
            $translator = $this->container->get(TranslationManager::class);

            new TranslationTwigExtension($view, $translator);
        }
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}