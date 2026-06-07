<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth;

use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Security\Auth\Exception\JwtException;

final class JwtManager
{
    private string $secret;
    private int $expiration;
    private string $algorithm;

    public function __construct(string $secret, int $expiration = 3600, string $algorithm = 'HS256')
    {
        if (empty($secret)) {
            throw new JwtException(
                title: 'JWT Configuration Error',
                message: "The JWT secret key cannot be empty.",
                code: 500
            );
        }

        $this->secret = $secret;
        $this->expiration = $expiration;
        $this->algorithm = $algorithm;
    }

    public function generate(array $payload = []): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => $this->algorithm,
        ]));

        $payload['iat'] = time();
        $payload['exp'] = time() + $this->expiration;

        $payload = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        return "$header.$payload.$signature";
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new JwtException(
                title: 'JWT Error',
                message: "Invalid token format.",
                code: 401
            );
        }

        [$header, $payload, $signature] = $parts;

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        if (!hash_equals($expectedSignature, $signature)) {
            throw new JwtException(
                title: 'JWT Error',
                message: "Invalid token signature.",
                code: 401
            );
        }

        $data = json_decode($this->base64UrlDecode($payload), true);

        if (!$data) {
            throw new JwtException(
                title: 'JWT Error',
                message: "Invalid token payload.",
                code: 401
            );
        }

        if (isset($data['exp']) && $data['exp'] < time()) {
            throw new JwtException(
                title: 'JWT Error',
                message: "The token has expired.",
                code: 401
            );
        }

        return $data;
    }

    public function isValid(string $token): bool
    {
        try {
            $this->decode($token);
            return true;
        } catch (JwtException) {
            return false;
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}