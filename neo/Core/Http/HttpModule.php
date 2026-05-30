<?php
declare(strict_types=1);

namespace Neo\Core\Http;

use Neo\Core\DI\Container;
use Neo\Core\Http\File\Uploader;
use Neo\Core\Http\Response\Response;
use Neo\Core\Module\AbstractModule;
use Neo\Core\Utils\Config\ConfigModule;

class HttpModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(Response::class, fn() => new Response());
        $container->set(Uploader::class, fn(Container $c) => new Uploader($c));
    }

    protected function resolveDependencies(): void
    {
        $this->get(Response::class);
        $this->get(Uploader::class);
    }
}