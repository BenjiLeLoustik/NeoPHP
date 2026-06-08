<?php
declare(strict_types=1);

namespace Neo\Core\Http\Tests\Response;

use Neo\Core\Http\Response\JsonResponse;
use Neo\Core\Http\Response\Response;
use PHPUnit\Framework\TestCase;

class JsonResponseTest extends TestCase
{
    public function testJsonResponseExtendsResponse(): void
    {
        $response = new JsonResponse([]);

        self::assertInstanceOf(Response::class, $response);
    }

    public function testDefaultStatusCodeIs200(): void
    {
        $response = new JsonResponse([]);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testCustomStatusCode(): void
    {
        $response = new JsonResponse([], 201);

        self::assertSame(201, $response->getStatusCode());
    }

    public function testStatusCode400(): void
    {
        $response = new JsonResponse(['error' => 'Bad Request'], 400);

        self::assertSame(400, $response->getStatusCode());
    }

    public function testContentTypeHeaderIsSetToJson(): void
    {
        $response = new JsonResponse([]);

        self::assertSame(
            'application/json; charset=utf-8',
            $response->getHeaders()['Content-Type']
        );
    }

    public function testArrayDataIsEncodedAsJson(): void
    {
        $data = ['key' => 'value', 'number' => 42];
        $response = new JsonResponse($data);

        self::assertSame(json_encode($data), $response->getContent());
    }

    public function testEmptyArrayProducesEmptyJsonObject(): void
    {
        $response = new JsonResponse([]);

        self::assertSame('[]', $response->getContent());
    }

    public function testNestedArrayIsEncodedCorrectly(): void
    {
        $data = ['user' => ['id' => 1, 'name' => 'Alice']];
        $response = new JsonResponse($data);

        self::assertSame(json_encode($data), $response->getContent());
    }

    public function testObjectDataIsEncodedAsJson(): void
    {
        $data = new \stdClass();
        $data->foo = 'bar';
        $data->num = 99;

        $response = new JsonResponse($data);

        self::assertSame(json_encode($data), $response->getContent());
    }

    public function testBooleanValuesAreEncoded(): void
    {
        $data = ['success' => true, 'active' => false];
        $response = new JsonResponse($data);

        $decoded = json_decode($response->getContent(), true);

        self::assertIsArray($decoded);
        self::assertTrue($decoded['success']);
        self::assertFalse($decoded['active']);
    }

    public function testNullValueInArrayIsEncoded(): void
    {
        $data = ['value' => null];
        $response = new JsonResponse($data);

        $decoded = json_decode($response->getContent(), true);

        self::assertIsArray($decoded);
        self::assertNull($decoded['value']);
    }

    public function testUnicodeStringIsEncodedCorrectly(): void
    {
        $data = ['message' => 'Héllo Wörld'];
        $response = new JsonResponse($data);

        $decoded = json_decode($response->getContent(), true);

        self::assertIsArray($decoded);
        self::assertSame('Héllo Wörld', $decoded['message']);
    }

    public function testSendOutputsJsonContent(): void
    {
        $data = ['status' => 'ok'];
        $response = new JsonResponse($data);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        self::assertSame(json_encode($data), $output);
    }

    public function testSuccessShapeCanBeCreated(): void
    {
        $response = new JsonResponse(['success' => true, 'data' => ['id' => 5]], 200);

        $decoded = json_decode($response->getContent(), true);

        self::assertIsArray($decoded);
        self::assertTrue($decoded['success']);
        self::assertSame(['id' => 5], $decoded['data']);
    }

    public function testErrorShapeCanBeCreated(): void
    {
        $response = new JsonResponse(['success' => false, 'error' => 'Not Found'], 404);

        $decoded = json_decode($response->getContent(), true);

        self::assertIsArray($decoded);
        self::assertFalse($decoded['success']);
        self::assertSame('Not Found', $decoded['error']);
        self::assertSame(404, $response->getStatusCode());
    }
}