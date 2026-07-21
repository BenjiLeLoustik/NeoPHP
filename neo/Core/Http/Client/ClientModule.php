<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Client\Cookie\Cookie;
use Neo\Core\Http\Client\Flash\Flash;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Http\Request\Request;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Utils\Config\ConfigModule;

class ClientModule implements ModuleInterface
{
    /**
     * @return array<class-string>
     */
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(Session::class, fn(Container $c) => new Session($c));
        $container->set(Cookie::class, fn(Container $c) => new Cookie($c));
        $container->set(Flash::class, fn(Container $c) => new Flash($c));
        $container->set(ClientManager::class, fn(Container $c) => new ClientManager($c));
    }

    /**
     * @throws ContainerException
     */
    public function init(Container $container): object
    {
        if (php_sapi_name() !== 'cli') {
            $container->get(Session::class);
            $container->get(Cookie::class);
            $container->get(Flash::class);

            $request = $container->get(Request::class);
            $request->enablePreviousUrlTracking($container->get(Session::class));
        }

        return $container->get(ClientManager::class);
    }
}