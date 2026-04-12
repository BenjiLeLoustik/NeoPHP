<?php
declare(strict_types=1);

namespace Neo\Core\Testing;

use Neo\App;
use Neo\Core\DI\Container;
use Neo\Core\Http\Request;
use Neo\Core\Http\Response\Response;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class FeatureTestCase extends PHPUnitTestCase
{
    protected Container $container;
    protected static ?App $app = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (static::$app === null) {
            static::$app = new App();
        }

        $this->container = static::$app->getContainer();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    protected function get(string $uri, array $headers = []): Response
    {
        return $this->request('GET', $uri, [], $headers);
    }

    protected function post(string $uri, array $body = [], array $headers = []): Response
    {
        return $this->request('POST', $uri, $body, $headers);
    }

    protected function put(string $uri, array $body = [], array $headers = []): Response
    {
        return $this->request('PUT', $uri, $body, $headers);
    }

    protected function delete(string $uri, array $headers = []): Response
    {
        return $this->request('DELETE', $uri, [], $headers);
    }

    protected function request(string $method, string $uri, array $body = [], array $headers = []): Response
    {
        $request = $this->buildRequest($method, $uri, $body, $headers);

        $this->container->set(Request::class, fn() => $request);

        $this->container->set(Response::class, fn() => new Response());

        return static::$app->run();
    }

    private function buildRequest(string $method, string $uri, array $body, array $headers): Request
    {
        $parsed  = parse_url($uri);
        $path    = $parsed['path'] ?? '/';
        $query   = [];

        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        $server = [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI'    => $uri,
            'SERVER_NAME'    => 'localhost',
            'SERVER_PORT'    => '80',
            'HTTP_HOST'      => 'localhost',
        ];

        foreach ($headers as $name => $value) {
            $key          = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $server[$key] = $value;
        }

        return Request::fromArray(
            method:  strtoupper($method),
            path:    $path,
            query:   $query,
            body:    $body,
            headers: $headers,
            server:  $server
        );
    }

    protected function assertStatus(int $expected, Response $response): void
    {
        $this->assertSame(
            $expected,
            $response->getStatusCode(),
            "Le status code attendu était $expected, obtenu : {$response->getStatusCode()}"
        );
    }

    protected function assertSeeText(string $text, Response $response): void
    {
        $this->assertStringContainsString(
            $text,
            $response->getContent(),
            "La réponse ne contient pas le texte attendu : '$text'"
        );
    }

    protected function assertJsonKey(string $key, Response $response): void
    {
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data, "La réponse n'est pas un JSON valide.");
        $this->assertArrayHasKey($key, $data, "La clé JSON '$key' est absente de la réponse.");
    }
}