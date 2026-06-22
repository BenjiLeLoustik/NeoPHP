<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth\Tests;

use Neo\Core\Security\Auth\Exception\JwtException;
use Neo\Core\Security\Auth\JwtManager;
use PHPUnit\Framework\TestCase;

final class JwtManagerTest extends TestCase
{
    private JwtManager $manager;

    /**
     * @throws JwtException
     */
    protected function setUp(): void
    {
        $this->manager = new JwtManager(secret: 'test-secret-key', expiration: 3600);
    }

    public function testConstructorThrowsWhenSecretIsEmpty(): void
    {
        try {
            new JwtManager(secret: '');
            self::fail('Expected JwtException was not thrown.');
        } catch (JwtException $e) {
            self::assertSame(500, $e->getCode());
        }
    }

    public function testGenerateReturnsThreePartToken(): void
    {
        $token = $this->manager->generate(['sub' => 42]);

        self::assertCount(3, explode('.', $token));
    }

    /**
     * @throws JwtException
     */
    public function testGenerateEmbeddsIatAndExp(): void
    {
        $before = time();
        $token = $this->manager->generate(['sub' => 1]);
        $after = time();

        $payload = $this->manager->decode($token);

        self::assertGreaterThanOrEqual($before, $payload['iat']);
        self::assertLessThanOrEqual($after, $payload['iat']);
        self::assertSame($payload['iat'] + 3600, $payload['exp']);
    }

    /**
     * @throws JwtException
     */
    public function testGeneratePreservesCustomClaims(): void
    {
        $token = $this->manager->generate(['sub' => 99, 'role' => 'admin']);
        $payload = $this->manager->decode($token);

        self::assertSame(99, $payload['sub']);
        self::assertSame('admin', $payload['role']);
    }

    public function testDecodeThrowsOnMalformedToken(): void
    {
        try {
            $this->manager->decode('not.a.valid.jwt.token');
            self::fail('Expected JwtException was not thrown.');
        } catch (JwtException $e) {
            self::assertSame(401, $e->getCode());
        }
    }

    public function testDecodeThrowsOnTamperedSignature(): void
    {
        $token = $this->manager->generate(['sub' => 1]);
        $parts = explode('.', $token);
        $parts[2] = 'invalidsignature';

        try {
            $this->manager->decode(implode('.', $parts));
            self::fail('Expected JwtException was not thrown.');
        } catch (JwtException $e) {
            self::assertSame(401, $e->getCode());
        }
    }

    public function testDecodeThrowsOnTamperedPayload(): void
    {
        $token = $this->manager->generate(['sub' => 1]);
        $parts = explode('.', $token);

        $parts[1] = rtrim(strtr(base64_encode(json_encode(['sub' => 999])), '+/', '-_'), '=');

        try {
            $this->manager->decode(implode('.', $parts));
            self::fail('Expected JwtException was not thrown.');
        } catch (JwtException $e) {
            self::assertSame(401, $e->getCode());
        }
    }

    /**
     * @throws JwtException
     */
    public function testDecodeThrowsOnExpiredToken(): void
    {
        $expired = new JwtManager(secret: 'test-secret-key', expiration: -1);
        $token = $expired->generate(['sub' => 1]);

        try {
            $expired->decode($token);
            self::fail('Expected JwtException was not thrown.');
        } catch (JwtException $e) {
            self::assertStringContainsString('expired', strtolower($e->getMessage()));
        }
    }

    public function testDecodeThrowsWhenSignedWithDifferentSecret(): void
    {
        $other = new JwtManager(secret: 'other-secret');
        $token = $other->generate(['sub' => 1]);

        try {
            $this->manager->decode($token);
            self::fail('Expected JwtException was not thrown.');
        } catch (JwtException $e) {
            self::assertSame(401, $e->getCode());
        }
    }

    public function testIsValidReturnsTrueForValidToken(): void
    {
        $token = $this->manager->generate(['sub' => 1]);

        self::assertTrue($this->manager->isValid($token));
    }

    public function testIsValidReturnsFalseForMalformedToken(): void
    {
        self::assertFalse($this->manager->isValid('garbage'));
    }

    /**
     * @throws JwtException
     */
    public function testIsValidReturnsFalseForExpiredToken(): void
    {
        $expired = new JwtManager(secret: 'test-secret-key', expiration: -1);
        $token = $expired->generate(['sub' => 1]);

        self::assertFalse($expired->isValid($token));
    }
}