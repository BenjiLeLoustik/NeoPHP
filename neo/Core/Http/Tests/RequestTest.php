<?php
declare(strict_types=1);

namespace Neo\Core\Http\Tests;

use Neo\Core\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    // --- fromArray (factory principale testable) ---

    private function makeRequest(
        string $method = 'GET',
        string $path = '/',
        array $query = [],
        array $body = [],
        array $headers = [],
        array $server = []
    ): Request {
        return Request::fromArray($method, $path, $query, $body, $headers, $server);
    }

    // --- createEmpty ---

    public function testCreateEmptyReturnsCLIRequest(): void
    {
        $request = Request::createEmpty();

        self::assertSame('CLI', $request->getMethod());
        self::assertSame('/', $request->getPath());
        self::assertSame([], $request->allQuery());
        self::assertSame([], $request->allBody());
        self::assertSame([], $request->headers());
        self::assertSame([], $request->allFiles());
    }

    // --- getMethod ---

    public function testGetMethodReturnsUppercased(): void
    {
        $request = $this->makeRequest('post');

        self::assertSame('POST', $request->getMethod());
    }

    public function testGetMethodGet(): void
    {
        $request = $this->makeRequest('GET');

        self::assertSame('GET', $request->getMethod());
    }

    public function testGetMethodDelete(): void
    {
        $request = $this->makeRequest('delete');

        self::assertSame('DELETE', $request->getMethod());
    }

    // --- getPath / sanitizePath ---

    public function testRootPathIsPreserved(): void
    {
        $request = $this->makeRequest('GET', '/');

        self::assertSame('/', $request->getPath());
    }

    public function testTrailingSlashIsStripped(): void
    {
        $request = $this->makeRequest('GET', '/users/');

        self::assertSame('/users', $request->getPath());
    }

    public function testLeadingSlashIsNormalized(): void
    {
        $request = $this->makeRequest('GET', 'about');

        self::assertSame('/about', $request->getPath());
    }

    public function testNestedPathIsPreserved(): void
    {
        $request = $this->makeRequest('GET', '/users/42/posts');

        self::assertSame('/users/42/posts', $request->getPath());
    }

    public function testMultipleLeadingSlashesAreNormalized(): void
    {
        $request = $this->makeRequest('GET', '///dashboard///');

        self::assertSame('/dashboard', $request->getPath());
    }

    // --- query ---

    public function testQueryReturnsValueByKey(): void
    {
        $request = $this->makeRequest('GET', '/', ['page' => '2']);

        self::assertSame('2', $request->query('page'));
    }

    public function testQueryReturnsDefaultWhenMissing(): void
    {
        $request = $this->makeRequest('GET', '/');

        self::assertNull($request->query('missing'));
        self::assertSame('default', $request->query('missing', 'default'));
    }

    public function testAllQueryReturnsFullArray(): void
    {
        $params = ['q' => 'php', 'page' => '1'];
        $request = $this->makeRequest('GET', '/', $params);

        self::assertSame($params, $request->allQuery());
    }

    // --- body ---

    public function testBodyReturnsValueByKey(): void
    {
        $request = $this->makeRequest('POST', '/', [], ['name' => 'Alice']);

        self::assertSame('Alice', $request->body('name'));
    }

    public function testBodyReturnsDefaultWhenMissing(): void
    {
        $request = $this->makeRequest('POST');

        self::assertNull($request->body('missing'));
        self::assertSame(0, $request->body('missing', 0));
    }

    public function testBodyWithNullKeyReturnsFullBody(): void
    {
        $body = ['a' => 1, 'b' => 2];
        $request = $this->makeRequest('POST', '/', [], $body);

        self::assertSame($body, $request->body());
    }

    public function testAllBodyReturnsFullArray(): void
    {
        $body = ['email' => 'a@b.com', 'password' => 'secret'];
        $request = $this->makeRequest('POST', '/', [], $body);

        self::assertSame($body, $request->allBody());
    }

    // --- getPost ---

    public function testGetPostReturnsValueByKey(): void
    {
        $request = $this->makeRequest('POST', '/', [], ['title' => 'Hello']);

        self::assertSame('Hello', $request->getPost('title'));
    }

    public function testGetPostWithNullKeyReturnsFullBody(): void
    {
        $body = ['x' => 1];
        $request = $this->makeRequest('POST', '/', [], $body);

        self::assertSame($body, $request->getPost());
    }

    public function testGetPostAllReturnsBody(): void
    {
        $body = ['foo' => 'bar'];
        $request = $this->makeRequest('POST', '/', [], $body);

        self::assertSame($body, $request->getPostAll());
    }

    // --- headers ---

    public function testHeaderReturnsValueByName(): void
    {
        $request = $this->makeRequest('GET', '/', [], [], ['Authorization' => 'Bearer token']);

        self::assertSame('Bearer token', $request->header('Authorization'));
    }

    public function testHeaderReturnsDefaultWhenMissing(): void
    {
        $request = $this->makeRequest('GET');

        self::assertNull($request->header('X-Missing'));
        self::assertSame('fallback', $request->header('X-Missing', 'fallback'));
    }

    public function testHeadersReturnsAllHeaders(): void
    {
        $headers = ['Content-Type' => 'application/json', 'X-Api-Key' => 'abc'];
        $request = $this->makeRequest('POST', '/', [], [], $headers);

        self::assertSame($headers, $request->headers());
    }

    // --- server ---

    public function testServerReturnsPassedArray(): void
    {
        $server = ['SERVER_NAME' => 'localhost', 'SERVER_PORT' => '80'];
        $request = $this->makeRequest('GET', '/', [], [], [], $server);

        self::assertSame($server, $request->server());
    }

    public function testGetServerReturnsPassedArray(): void
    {
        $server = ['REMOTE_ADDR' => '127.0.0.1'];
        $request = $this->makeRequest('GET', '/', [], [], [], $server);

        self::assertSame($server, $request->getServer());
    }

    // --- getFullUrl ---

    public function testGetFullUrlWithNoQueryString(): void
    {
        $request = $this->makeRequest('GET', '/about');

        self::assertSame('/about', $request->getFullUrl());
    }

    public function testGetFullUrlWithQueryString(): void
    {
        $request = $this->makeRequest('GET', '/search', ['q' => 'php', 'page' => '2']);

        $url = $request->getFullUrl();

        // http_build_query ne garantit pas l'ordre, on vérifie séparément
        self::assertStringStartsWith('/search?', $url);
        self::assertStringContainsString('q=php', $url);
        self::assertStringContainsString('page=2', $url);
    }

    public function testGetFullUrlRootWithNoQuery(): void
    {
        $request = $this->makeRequest('GET', '/');

        self::assertSame('/', $request->getFullUrl());
    }

    // --- getIp ---

    public function testGetIpFromRemoteAddr(): void
    {
        $request = $this->makeRequest('GET', '/', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);

        self::assertSame('192.168.1.1', $request->getIp());
    }

    public function testGetIpPrioritizesCfConnectingIp(): void
    {
        $request = $this->makeRequest('GET', '/', [], [], [], [
            'HTTP_CF_CONNECTING_IP' => '1.2.3.4',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        self::assertSame('1.2.3.4', $request->getIp());
    }

    public function testGetIpPrioritizesXRealIpOverXForwardedFor(): void
    {
        $request = $this->makeRequest('GET', '/', [], [], [], [
            'HTTP_X_REAL_IP' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '10.0.0.2',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        self::assertSame('10.0.0.1', $request->getIp());
    }

    public function testGetIpExtractsFirstIpFromXForwardedFor(): void
    {
        $request = $this->makeRequest('GET', '/', [], [], [], [
            'HTTP_X_FORWARDED_FOR' => '5.5.5.5, 6.6.6.6, 7.7.7.7',
        ]);

        self::assertSame('5.5.5.5', $request->getIp());
    }

    public function testGetIpReturnsNullWhenNoServerData(): void
    {
        $request = $this->makeRequest('GET', '/', [], [], [], []);

        self::assertNull($request->getIp());
    }

    // --- getUserAgent ---

    public function testGetUserAgentReturnsValue(): void
    {
        $request = $this->makeRequest('GET', '/', [], [], [], ['HTTP_USER_AGENT' => 'Mozilla/5.0']);

        self::assertSame('Mozilla/5.0', $request->getUserAgent());
    }

    public function testGetUserAgentReturnsNullWhenAbsent(): void
    {
        $request = $this->makeRequest('GET');

        self::assertNull($request->getUserAgent());
    }

    // --- file / allFiles ---

    public function testAllFilesReturnsEmptyArrayWhenNoFiles(): void
    {
        $request = $this->makeRequest('POST');

        self::assertSame([], $request->allFiles());
    }

    public function testFileReturnsNullForMissingKey(): void
    {
        $request = $this->makeRequest('POST');

        self::assertNull($request->file('avatar'));
    }
}