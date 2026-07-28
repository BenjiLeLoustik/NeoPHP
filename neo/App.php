<?php
declare(strict_types=1);

namespace Neo;

use Neo\Core\Application\ApplicationDetector;
use Neo\Core\Application\ApplicationPaths;
use Neo\Core\Application\Exception\ApplicationException;
use Neo\Core\DI\Container;
use Neo\Core\DI\ContainerRegistry;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Event\Event\RequestEvent;
use Neo\Core\Event\Event\ResponseEvent;
use Neo\Core\Event\EventManager;
use Neo\Core\Http\Request\Request;
use Neo\Core\Http\Response\Types\Response;
use Neo\Core\Module\Exception\ModuleException;
use Neo\Core\Module\ModuleManager;
use Neo\Core\Routing\RouterManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class App
{
    private Container $container;

    /**
     * @throws NotFoundExceptionInterface
     * @throws ModuleException
     * @throws ContainerExceptionInterface
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function __construct()
    {
        $this->container = new Container();
        $this->container->set(Container::class, fn() => $this->container);

        ContainerRegistry::set($this->container);

        $this->container->set(Request::class, fn() => php_sapi_name() === 'cli'
            ? Request::createEmpty()
            : Request::fromGlobals()
        );

        new ApplicationDetector($this->container)->detect();
        new ApplicationPaths($this->container)->register();

        $moduleManager = new ModuleManager($this->container)
            ->discover(__DIR__ . '/Core')
            ->discover($this->container->get('appPath'));

        $moduleManager->boot();

        $viewManager = $this->container->get(\Neo\Core\View\ViewManager::class);
        $extensions = $this->container->get(\Neo\Core\Extension\ExtensionManager::class)->getViewExtensions();

        foreach ($extensions as $extension) {
            $viewManager->addExtension($extension);
        }
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws \JsonException
     * @throws ContainerException
     */
    public function run(): Response
    {
        $request = $this->container->get(Request::class);
        $response = $this->container->get(Response::class);
        $router = $this->container->get(RouterManager::class);
        $dispatcher = $this->container->get(EventManager::class);

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

    public function getContainer(): Container
    {
        return $this->container;
    }
}