<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client;

use Neo\Core\DI\Container;
use Neo\Core\Http\Client\Cookie\Cookie;
use Neo\Core\Http\Client\Flash\Flash;
use Neo\Core\Http\Client\Flash\FlashViewExtension;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Http\HttpModule;
use Neo\Core\Http\Request;
use Neo\Core\Module\Abstract\AbstractModule;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\View\ViewModule;

class ClientModule extends AbstractModule
{
    /**
     * @return array<class-string>
     */
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            HttpModule::class,
            ViewModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(Session::class, fn(Container $c) => new Session($c));
        $container->set(Cookie::class, fn(Container $c) => new Cookie($c));
        $container->set(Flash::class, fn(Container $c) => new Flash($c));

        $container->set(FlashViewExtension::class, fn(Container $c) => new FlashViewExtension($c->get(Flash::class)));
        $container->tag(FlashViewExtension::class, 'twig.extension');
    }

    protected function resolveDependencies(): void
    {
        $this->get(Session::class);
        $this->get(Cookie::class);

        if (php_sapi_name() !== 'cli') {
            $this->get(Flash::class);

            $request = $this->get(Request::class);
            $request->enablePreviousUrlTracking($this->get(Session::class));
        }
    }
}