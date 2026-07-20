<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client;

use Neo\Core\DI\Container;
use Neo\Core\Http\Client\Cookie\Cookie;
use Neo\Core\Http\Client\Flash\Flash;
use Neo\Core\Http\Client\Session\Session;

final class ClientManager
{
    public function __construct(private readonly Container $container) {}

    public function session(): Session
    {
        return $this->container->get(Session::class);
    }

    public function cookie(): Cookie
    {
        return $this->container->get(Cookie::class);
    }

    public function flash(): Flash
    {
        return $this->container->get(Flash::class);
    }
}