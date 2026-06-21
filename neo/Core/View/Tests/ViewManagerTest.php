<?php
declare(strict_types=1);

namespace Neo\Core\View\Tests;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\View\Exception\ViewException;
use Neo\Core\View\Tests\Fixture\Extensions\UppercaseExtension;
use Neo\Core\View\ViewManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class ViewManagerTest extends TestCase
{
    private string $configsDir;
    private string $viewsDir;
    private string $storageDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/neo-view-test-' . uniqid();

        $this->configsDir = $base . '/configs';
        $this->viewsDir = $base . '/views';
        $this->storageDir = $base . '/storage';

        mkdir($this->configsDir, 0777, true);
        mkdir($this->viewsDir, 0777, true);
        mkdir($this->storageDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir(dirname($this->configsDir));
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    /**
     * @param array<string, mixed> $twigConfig
     * @param array<string, mixed> $appConfig
     */
    private function makeContainer(array $twigConfig = [], array $appConfig = []): Container
    {
        $container = new Container();
        $container->instance(Container::class, $container);

        $twigConfig = array_merge([
            'cache' => false,
            'debug' => false,
            'auto_reload' => true,
            'auto_escape' => 'html',
            'charset' => 'UTF-8',
            'strict_variables' => false,
        ], $twigConfig);

        $appConfig = array_merge([
            'environment' => 'dev',
            'date' => ['timezone' => 'UTC'],
            'general' => ['name' => 'TestApp'],
        ], $appConfig);

        file_put_contents(
            $this->configsDir . '/app.config.php',
            '<?php return ' . var_export($appConfig, true) . ';'
        );

        file_put_contents(
            $this->configsDir . '/twig.config.php',
            '<?php return ' . var_export($twigConfig, true) . ';'
        );

        $container->set('configsPath', $this->configsDir);
        $container->set('viewsPath', $this->viewsDir);
        $container->set('storagePath', $this->storageDir);

        return $container;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ViewException
     * @throws ContainerException
     */
    public function testRenderReturnsCompiledTemplate(): void
    {
        file_put_contents($this->viewsDir . '/hello.html.twig', 'Hello {{ name }}!');

        $manager = new ViewManager($this->makeContainer());

        self::assertSame('Hello World!', $manager->render('hello.html.twig', ['name' => 'World']));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ContainerException
     */
    public function testRenderThrowsViewExceptionWhenTemplateIsMissing(): void
    {
        $manager = new ViewManager($this->makeContainer());

        $this->expectException(ViewException::class);

        $manager->render('missing.html.twig');
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ContainerException
     */
    public function testRenderThrowsViewExceptionOnSyntaxError(): void
    {
        file_put_contents($this->viewsDir . '/broken.html.twig', '{% if %}');

        $manager = new ViewManager($this->makeContainer());

        $this->expectException(ViewException::class);

        $manager->render('broken.html.twig');
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ContainerException
     */
    public function testRenderIfExistsReturnsNullWhenTemplateIsMissing(): void
    {
        $manager = new ViewManager($this->makeContainer());

        self::assertNull($manager->renderIfExists('missing.html.twig'));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ContainerException
     */
    public function testRenderIfExistsReturnsContentWhenTemplateExists(): void
    {
        file_put_contents($this->viewsDir . '/ok.html.twig', 'OK');

        $manager = new ViewManager($this->makeContainer());

        self::assertSame('OK', $manager->renderIfExists('ok.html.twig'));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ViewException
     * @throws ContainerException
     */
    public function testAddExtensionRegistersFunctionsAndFilters(): void
    {
        file_put_contents(
            $this->viewsDir . '/extension.html.twig',
            '{{ shout(name) }} - {{ name|reverse_str }}'
        );

        $manager = new ViewManager($this->makeContainer());
        $manager->addExtension(new UppercaseExtension());

        self::assertSame(
            'NEO! - oeN',
            $manager->render('extension.html.twig', ['name' => 'Neo'])
        );
    }

    /**
     * @throws ContainerException
     */
    public function testGetTwigExposesAppGlobalFromAppConfig(): void
    {
        $manager = new ViewManager($this->makeContainer(appConfig: [
            'general' => ['name' => 'MyApp'],
        ]));

        self::assertSame(['name' => 'MyApp'], $manager->getTwig()->getGlobals()['app']);
    }
}