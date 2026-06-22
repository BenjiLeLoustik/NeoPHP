<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth\Tests;

use Neo\Core\Security\Auth\PasswordManager;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

final class PasswordManagerTest extends TestCase
{
    private PasswordManager $manager;

    protected function setUp(): void
    {
        $this->manager = new PasswordManager();
    }

    public function testHashReturnsNonEmptyString(): void
    {
        $hash = $this->manager->hash('secret');

        self::assertNotEmpty($hash);
        self::assertNotSame('secret', $hash);
    }

    public function testHashProducesDifferentResultsForSameInput(): void
    {
        $hash1 = $this->manager->hash('secret');
        $hash2 = $this->manager->hash('secret');

        self::assertNotSame($hash1, $hash2);
    }

    public function testVerifyReturnsTrueForCorrectPassword(): void
    {
        $hash = $this->manager->hash('correct-password');

        self::assertTrue($this->manager->verify('correct-password', $hash));
    }

    public function testVerifyReturnsFalseForWrongPassword(): void
    {
        $hash = $this->manager->hash('correct-password');

        self::assertFalse($this->manager->verify('wrong-password', $hash));
    }

    public function testNeedsRehashReturnsFalseForFreshHash(): void
    {
        $hash = $this->manager->hash('secret');

        self::assertFalse($this->manager->needsRehash($hash));
    }

    /**
     * @throws RandomException
     */
    public function testGenerateReturnsHexStringOfExpectedLength(): void
    {
        $token = $this->manager->generate(12);

        self::assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
        self::assertSame(24, strlen($token));
    }

    /**
     * @throws RandomException
     */
    public function testGenerateReturnsDifferentValuesEachCall(): void
    {
        $a = $this->manager->generate();
        $b = $this->manager->generate();

        self::assertNotSame($a, $b);
    }

    public function testGetInfoReturnsAlgoAndOptions(): void
    {
        $hash = $this->manager->hash('secret');
        $info = $this->manager->getInfo($hash);

        self::assertArrayHasKey('algo', $info);
        self::assertArrayHasKey('options', $info);
    }
}