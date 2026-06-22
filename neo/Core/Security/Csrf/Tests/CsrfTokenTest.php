<?php
declare(strict_types=1);

namespace Neo\Core\Security\Csrf\Tests;

use Neo\Core\Security\Csrf\Token\CsrfToken;
use PHPUnit\Framework\TestCase;

final class CsrfTokenTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $token = new CsrfToken('form_login', 'abc123');

        self::assertSame('form_login', $token->getId());
        self::assertSame('abc123', $token->getValue());
    }

    public function testTokenIsNotExpiredWhenFreshlyCreated(): void
    {
        $token = new CsrfToken('form_login', 'abc123', 3600);

        self::assertFalse($token->isExpired());
    }

    public function testTokenIsExpiredWhenExpiryIsZero(): void
    {
        $token = new CsrfToken('form_login', 'abc123', 0);

        sleep(1);

        self::assertTrue($token->isExpired());
    }

    public function testTokenIsExpiredWhenExpiryIsNegative(): void
    {
        $token = new CsrfToken('form_login', 'abc123', -3600);

        self::assertTrue($token->isExpired());
    }
}