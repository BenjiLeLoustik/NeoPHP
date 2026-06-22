<?php
declare(strict_types=1);

namespace Neo\Core\Security\Csrf\Tests;

use Neo\Core\Security\Csrf\CsrfTokenManager;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

final class CsrfTokenManagerTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * @throws RandomException
     */
    public function testGenerateTokenReturnsTokenWithCorrectId(): void
    {
        $manager = new CsrfTokenManager();
        $token = $manager->generateToken('contact_form');

        self::assertSame('contact_form', $token->getId());
    }

    /**
     * @throws RandomException
     */
    public function testGenerateTokenValueIsHexString(): void
    {
        $manager = new CsrfTokenManager();
        $token = $manager->generateToken('contact_form');

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token->getValue());
    }

    /**
     * @throws RandomException
     */
    public function testGenerateTokenStoresTokenInSession(): void
    {
        $manager = new CsrfTokenManager();
        $token = $manager->generateToken('contact_form');

        self::assertSame($token, $manager->getToken('contact_form'));
    }

    public function testGetTokenReturnsNullWhenTokenDoesNotExist(): void
    {
        $manager = new CsrfTokenManager();

        self::assertNull($manager->getToken('unknown_form'));
    }

    /**
     * @throws RandomException
     */
    public function testValidateTokenReturnsTrueForCorrectValue(): void
    {
        $manager = new CsrfTokenManager();
        $token = $manager->generateToken('login_form');

        self::assertTrue($manager->validateToken('login_form', $token->getValue()));
    }

    /**
     * @throws RandomException
     */
    public function testValidateTokenReturnsFalseForWrongValue(): void
    {
        $manager = new CsrfTokenManager();
        $manager->generateToken('login_form');

        self::assertFalse($manager->validateToken('login_form', 'wrong-value'));
    }

    public function testValidateTokenReturnsFalseForUnknownId(): void
    {
        $manager = new CsrfTokenManager();

        self::assertFalse($manager->validateToken('ghost_form', 'any-value'));
    }

    /**
     * @throws RandomException
     */
    public function testValidateTokenInvalidatesAfterSuccessfulValidationByDefault(): void
    {
        $manager = new CsrfTokenManager();
        $token = $manager->generateToken('checkout_form');
        $value = $token->getValue();

        $manager->validateToken('checkout_form', $value);

        self::assertNull($manager->getToken('checkout_form'));

        self::assertFalse($manager->validateToken('checkout_form', $value));
    }

    /**
     * @throws RandomException
     */
    public function testValidateTokenKeepsTokenWhenInvalidateFalse(): void
    {
        $manager = new CsrfTokenManager();
        $token = $manager->generateToken('search_form');
        $value = $token->getValue();

        $manager->validateToken('search_form', $value, invalidate: false);

        self::assertNotNull($manager->getToken('search_form'));
        self::assertTrue($manager->validateToken('search_form', $value, invalidate: false));
    }

    /**
     * @throws RandomException
     */
    public function testValidateTokenReturnsFalseAndRemovesExpiredToken(): void
    {
        $manager = new CsrfTokenManager();
        $token = $manager->generateToken('old_form', expiry: -1);
        $value = $token->getValue();

        self::assertFalse($manager->validateToken('old_form', $value));
        self::assertNull($manager->getToken('old_form'));
    }
}