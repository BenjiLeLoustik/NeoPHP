<?php
declare(strict_types=1);

namespace Neo\Core\Http\Tests\Response;

use Neo\Core\Http\Response\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    private Response $response;

    protected function setUp(): void
    {
        $this->response = new Response();
    }

    public function testDefaultStatusCodeIs200(): void
    {
        self::assertSame(200, $this->response->getStatusCode());
    }

    public function testSetStatusCodeReturnsStaticAndUpdatesCode(): void
    {
        $result = $this->response->setStatusCode(404);

        self::assertSame($this->response, $result);
        self::assertSame(404, $this->response->getStatusCode());
    }

    public function testSetStatusCodeWithVariousCodes(): void
    {
        foreach ([200, 201, 301, 302, 400, 401, 403, 404, 500] as $code) {
            $this->response->setStatusCode($code);
            self::assertSame($code, $this->response->getStatusCode());
        }
    }

    public function testDefaultHeadersAreEmpty(): void
    {
        self::assertSame([], $this->response->getHeaders());
    }

    public function testSetHeaderReturnsStaticAndStoresHeader(): void
    {
        $result = $this->response->setHeader('Content-Type', 'text/html');

        self::assertSame($this->response, $result);
        self::assertSame(['Content-Type' => 'text/html'], $this->response->getHeaders());
    }

    public function testSetHeaderOverwritesExistingValue(): void
    {
        $this->response->setHeader('X-Foo', 'first');
        $this->response->setHeader('X-Foo', 'second');

        self::assertSame('second', $this->response->getHeaders()['X-Foo']);
    }

    public function testMultipleHeadersAreStoredIndependently(): void
    {
        $this->response->setHeader('Content-Type', 'application/json');
        $this->response->setHeader('X-Request-Id', 'abc-123');

        $headers = $this->response->getHeaders();

        self::assertArrayHasKey('Content-Type', $headers);
        self::assertArrayHasKey('X-Request-Id', $headers);
        self::assertCount(2, $headers);
    }

    public function testAddHeaderCreatesHeaderWhenAbsent(): void
    {
        $result = $this->response->addHeader('Accept', 'text/html');

        self::assertSame($this->response, $result);
        self::assertSame('text/html', $this->response->getHeaders()['Accept']);
    }

    public function testAddHeaderAppendsWhenHeaderAlreadyExists(): void
    {
        $this->response->addHeader('Accept', 'text/html');
        $this->response->addHeader('Accept', 'application/json');

        self::assertSame('text/html, application/json', $this->response->getHeaders()['Accept']);
    }

    public function testAddHeaderAppendsMultipleTimes(): void
    {
        $this->response->addHeader('Vary', 'Accept');
        $this->response->addHeader('Vary', 'Accept-Encoding');
        $this->response->addHeader('Vary', 'Origin');

        self::assertSame('Accept, Accept-Encoding, Origin', $this->response->getHeaders()['Vary']);
    }

    public function testDefaultContentIsEmptyString(): void
    {
        self::assertSame('', $this->response->getContent());
    }

    public function testSetContentReturnsStaticAndUpdatesContent(): void
    {
        $result = $this->response->setContent('<html></html>');

        self::assertSame($this->response, $result);
        self::assertSame('<html></html>', $this->response->getContent());
    }

    public function testSetContentWithEmptyString(): void
    {
        $this->response->setContent('something');
        $this->response->setContent('');

        self::assertSame('', $this->response->getContent());
    }

    public function testMethodChainingWorks(): void
    {
        $result = $this->response
            ->setStatusCode(201)
            ->setHeader('Content-Type', 'text/plain')
            ->setContent('Created');

        self::assertSame($this->response, $result);
        self::assertSame(201, $this->response->getStatusCode());
        self::assertSame('text/plain', $this->response->getHeaders()['Content-Type']);
        self::assertSame('Created', $this->response->getContent());
    }

    public function testSendOutputsContent(): void
    {
        $this->response->setContent('Hello, World!');

        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        self::assertSame('Hello, World!', $output);
    }

    public function testSendOutputsEmptyStringWhenNoContent(): void
    {
        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        self::assertSame('', $output);
    }

    public function testSendOutputsJsonContent(): void
    {
        $json = '{"key":"value"}';
        $this->response->setContent($json);

        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        self::assertSame($json, $output);
    }
}