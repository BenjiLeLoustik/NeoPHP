<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Tests;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Routing\Collection\RouteCollection;
use Neo\Core\Routing\Exception\RouteNotFoundException;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private string $tmpDir;
    private Container $container;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/neo-router-test-' . uniqid();

        $configsDir = $this->tmpDir . '/configs';
        $controllersDir = $this->tmpDir . '/controllers';
        $storageDir = $this->tmpDir . '/storage';

        mkdir($configsDir, 0777, true);
        mkdir($controllersDir, 0777, true);
        mkdir($storageDir, 0777, true);

        file_put_contents(
            $configsDir . '/app.config.php',
            '<?php return ["environment" => "dev"];'
        );

        $this->container = new Container();
        $this->container->instance(Container::class, $this->container);
        $this->container->set('configsPath', $configsDir);
        $this->container->set('controllersPath', $controllersDir);
        $this->container->set('storagePath', $storageDir);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tmpDir);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * @throws ContainerException
     */
    private function makeRouter(): TestableRouter
    {
        return new TestableRouter($this->container);
    }

    private function makeCollection(): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->add('GET', '/users', 'user.index', 'UserController', 'index');
        $collection->add('GET', '/users/{id}', 'user.show', 'UserController', 'show', ['id' => '[0-9]+']);
        $collection->add('GET', '/posts/{slug?}', 'post.show', 'PostController', 'show');
        return $collection;
    }

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    public function testStaticRouteMatchesExactPath(): void
    {
        $router = $this->makeRouter();
        $pattern = $router->exposeCompilePattern('/users');

        self::assertSame(1, preg_match($pattern, '/users'));
        self::assertSame(0, preg_match($pattern, '/users/extra'));
    }

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    public function testDynamicSegmentCaptures(): void
    {
        $router = $this->makeRouter();
        $pattern = $router->exposeCompilePattern('/users/{id}');

        self::assertSame(1, preg_match($pattern, '/users/42', $m));
        self::assertSame('42', $m['id']);
    }

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    public function testRequirementRestrictsMatch(): void
    {
        $router = $this->makeRouter();
        $pattern = $router->exposeCompilePattern('/users/{id}', ['id' => '[0-9]+']);

        self::assertSame(1, preg_match($pattern, '/users/99'));
        self::assertSame(0, preg_match($pattern, '/users/abc'));
    }

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    public function testInvalidRequirementFallsBackToDefaultPattern(): void
    {
        $router = $this->makeRouter();

        $pattern = $router->exposeCompilePattern('/users/{id}', ['id' => '[invalid(']);

        self::assertSame(1, preg_match($pattern, '/users/anything'));
    }

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    public function testOptionalSegmentMatchesWithAndWithoutValue(): void
    {
        $router = $this->makeRouter();
        $pattern = $router->exposeCompilePattern('/posts/{slug?}');

        self::assertSame(1, preg_match($pattern, '/posts'));
        self::assertSame(1, preg_match($pattern, '/posts/my-post'));
    }

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    public function testTrailingSlashIsAccepted(): void
    {
        $router = $this->makeRouter();
        $pattern = $router->exposeCompilePattern('/users');

        self::assertSame(1, preg_match($pattern, '/users/'));
    }

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    public function testCompiledPatternIsCached(): void
    {
        $router = $this->makeRouter();

        $first = $router->exposeCompilePattern('/users');
        $second = $router->exposeCompilePattern('/users');

        self::assertSame($first, $second);
    }

    /**
     * @throws ContainerException
     * @throws RouteNotFoundException
     */
    public function testGenerateUrlForStaticRoute(): void
    {
        $router = $this->makeRouter();
        $router->seedRoutes($this->makeCollection());

        self::assertSame('/users', $router->generateUrl('user.index'));
    }

    /**
     * @throws ContainerException
     * @throws RouteNotFoundException
     */
    public function testGenerateUrlInjectsRequiredParam(): void
    {
        $router = $this->makeRouter();
        $router->seedRoutes($this->makeCollection());

        self::assertSame('/users/42', $router->generateUrl('user.show', ['id' => '42']));
    }

    /**
     * @throws ContainerException
     * @throws RouteNotFoundException
     */
    public function testGenerateUrlDropsOptionalParamWhenNotProvided(): void
    {
        $router = $this->makeRouter();
        $router->seedRoutes($this->makeCollection());

        self::assertSame('/posts', $router->generateUrl('post.show'));
    }

    /**
     * @throws ContainerException
     * @throws RouteNotFoundException
     */
    public function testGenerateUrlInjectsOptionalParamWhenProvided(): void
    {
        $router = $this->makeRouter();
        $router->seedRoutes($this->makeCollection());

        self::assertSame('/posts/my-post', $router->generateUrl('post.show', ['slug' => 'my-post']));
    }

    /**
     * @throws ContainerException
     */
    public function testGenerateUrlThrowsWhenRouteNameIsUnknown(): void
    {
        $router = $this->makeRouter();
        $router->seedRoutes($this->makeCollection());

        try {
            $router->generateUrl('does.not.exist');
            self::fail('Expected RouteNotFoundException was not thrown.');
        } catch (RouteNotFoundException $e) {
            self::assertStringContainsString("does.not.exist", $e->getMessage());
        }
    }

    /**
     * @throws ContainerException
     */
    public function testGetRoutesReturnsSeededCollection(): void
    {
        $router = $this->makeRouter();
        $router->seedRoutes($this->makeCollection());

        self::assertTrue($router->getRoutes()->has('GET', '/users'));
    }

    /**
     * @throws ContainerException
     */
    public function testCurrentRouteNameIsNullInitially(): void
    {
        $router = $this->makeRouter();

        self::assertNull($router->getCurrentRouteName());
    }
}