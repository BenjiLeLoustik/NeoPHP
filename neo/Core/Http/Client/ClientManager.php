<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Client\Cookie\Cookie;
use Neo\Core\Http\Client\Flash\Flash;
use Neo\Core\Http\Client\Session\Session;
use ReflectionException;

final class ClientManager
{
    public function __construct(
        private Container $container
    ) {
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function session(): Session
    {
        return $this->container->get(Session::class);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function cookie(): Cookie
    {
        return $this->container->get(Cookie::class);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function flash(): Flash
    {
        return $this->container->get(Flash::class);
    }
}