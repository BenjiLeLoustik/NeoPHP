<?php
declare(strict_types=1);

namespace Neo\Core\Profiler;

use Neo\Core\DI\Container;
use Neo\Core\Event\EventDispatcher;
use Neo\Core\Event\EventModule;
use Neo\Core\Event\Event\ResponseEvent;
use Neo\Core\Http\HttpModule;
use Neo\Core\Http\Request;
use Neo\Core\Module\AbstractModule;
use Neo\Core\Profiler\Collector\AuthCollector;
use Neo\Core\Profiler\Collector\EventCollector;
use Neo\Core\Profiler\Collector\LogCollector;
use Neo\Core\Profiler\Collector\MailCollector;
use Neo\Core\Profiler\Collector\QueryCollector;
use Neo\Core\Profiler\Collector\RequestCollector;
use Neo\Core\Profiler\Collector\RouterCollector;
use Neo\Core\Profiler\Collector\TranslationCollector;
use Neo\Core\Profiler\Toolbar\Toolbar;
use Neo\Core\Routing\Router;
use Neo\Core\Routing\RouterModule;
use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\Security\SecurityModule;
use Neo\Core\Translation\TranslationManager;
use Neo\Core\Translation\TranslationModule;
use Neo\Core\Utils\Config\Config;
use Neo\Core\Utils\Mailer\Mailer;
use Neo\Core\Utils\Mailer\MailerModule;

class ProfilerModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [
            HttpModule::class,
            EventModule::class,
            RouterModule::class,
            SecurityModule::class,
            TranslationModule::class,
            MailerModule::class,
        ];
    }

    public function register(Container $container): void
    {}

    protected function resolveDependencies(): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }

        $env = $this->get(Config::class)->from('app')->get('environment') ?? 'prod';

        if ($env !== 'dev') {
            return;
        }

        if (!defined('NEO_PROFILER_ENABLED')) {
            define('NEO_PROFILER_ENABLED', true);
        }

        $request = $this->get(Request::class);
        $router = $this->get(Router::class);
        $dispatcher = $this->get(EventDispatcher::class);
        $auth = $this->get(AuthManager::class);
        $translator = $this->get(TranslationManager::class);
        $mailer = $this->get(Mailer::class);

        $profiler = Profiler::getInstance();
        $profiler->addCollector(new RequestCollector($request));
        $profiler->addCollector(new RouterCollector($router));
        $profiler->addCollector(new QueryCollector());
        $profiler->addCollector(new EventCollector($dispatcher));
        $profiler->addCollector(new LogCollector());
        $profiler->addCollector(new AuthCollector($auth));
        $profiler->addCollector(new TranslationCollector($translator));
        $profiler->addCollector(new MailCollector($mailer));

        $toolbar = new Toolbar($profiler);
        $listener = new ProfilerResponseListener($toolbar);
        $dispatcher->addListenerInstance(ResponseEvent::class, $listener, 'onResponse');
    }
}