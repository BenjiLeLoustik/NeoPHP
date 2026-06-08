<?php
declare(strict_types=1);

namespace Neo\Core\Http\Tests\Response;

use Neo\Core\Http\Response\RedirectResponse;
use Neo\Core\Http\Response\Response;
use PHPUnit\Framework\TestCase;

class RedirectResponseTest extends TestCase
{
    public function testRedirectResponseExtendsResponse(): void
    {
        $response = new RedirectResponse('/home');

        self::assertInstanceOf(Response::class, $response);
    }

    public function testDefaultStatusCodeIs302(): void
    {
        $response = new RedirectResponse('/home');

        self::assertSame(302, $response->getStatusCode());
    }

    public function testCustomStatusCode301(): void
    {
        $response = new RedirectResponse('/home', 301);

        self::assertSame(301, $response->getStatusCode());
    }

    public function testCustomStatusCode307(): void
    {
        $response = new RedirectResponse('/tmp', 307);

        self::assertSame(307, $response->getStatusCode());
    }

    public function testCustomStatusCode308(): void
    {
        $response = new RedirectResponse('/new-location', 308);

        self::assertSame(308, $response->getStatusCode());
    }

    public function testLocationHeaderIsSet(): void
    {
        $response = new RedirectResponse('/dashboard');

        self::assertArrayHasKey('Location', $response->getHeaders());
        self::assertSame('/dashboard', $response->getHeaders()['Location']);
    }

    public function testLocationHeaderWithAbsoluteUrl(): void
    {
        $url = 'https://example.com/login';
        $response = new RedirectResponse($url);

        self::assertSame($url, $response->getHeaders()['Location']);
    }

    public function testLocationHeaderWithQueryString(): void
    {
        $url = '/search?q=php&page=2';
        $response = new RedirectResponse($url);

        self::assertSame($url, $response->getHeaders()['Location']);
    }

    public function testLocationHeaderWithFragment(): void
    {
        $url = '/page#section';
        $response = new RedirectResponse($url);

        self::assertSame($url, $response->getHeaders()['Location']);
    }

    public function testContentIsEmptyString(): void
    {
        $response = new RedirectResponse('/home');

        self::assertSame('', $response->getContent());
    }

    public function testPermanentRedirect(): void
    {
        $response = new RedirectResponse('https://new-domain.com/', 301);

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('https://new-domain.com/', $response->getHeaders()['Location']);
        self::assertSame('', $response->getContent());
    }

    public function testTemporaryRedirectWithPath(): void
    {
        $response = new RedirectResponse('/auth/login', 302);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaders()['Location']);
    }

    public function testSendOutputsEmptyBody(): void
    {
        $response = new RedirectResponse('/somewhere');

        ob_start();
        $response->send();
        $output = ob_get_clean();

        self::assertSame('', $output);
    }
}