<?php
declare(strict_types=1);

namespace Neo;

use Neo\Core\Application\ApplicationDetector;
use Neo\Core\Application\ApplicationPaths;
use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\DI\Container;
use Neo\Core\Event\Event\RequestEvent;
use Neo\Core\Event\Event\ResponseEvent;
use Neo\Core\Event\EventDispatcher;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Http\Request;
use Neo\Core\Http\Response\Response;
use Neo\Core\Module\ModuleManager;
use Neo\Core\Routing\Router;

class App
{
    private Container $container;

    public function __construct()
    {
        $this->container = new Container();
        $this->container->set(Container::class, fn() => $this->container);

        $this->container->set(Request::class, fn() => php_sapi_name() === 'cli'
            ? Request::createEmpty()
            : Request::fromGlobals()
        );

        (new ApplicationDetector($this->container))->detect();
        (new ApplicationPaths($this->container))->register();

        (new ModuleManager($this->container))
            ->discover(__DIR__ . '/Core')
            ->boot();

        if (php_sapi_name() !== 'cli') {
            $this->container->get(Request::class)
                ->enablePreviousUrlTracking(
                    $this->container->get(Session::class)
                );
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

    public function getContainer(): Container
    {
        return $this->container;
    }
}