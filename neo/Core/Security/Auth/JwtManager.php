<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth;

use Neo\Core\Security\Auth\Exception\JwtException;

final class JwtManager
{
    private string $secret;
    private int $expiration;
    private string $algorithm;

    /**
     * @throws JwtException
     */
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

    /**
     * @param array<string, mixed> $payload
     */
    public function generate(array $payload = []): string
    {
        $header = ['typ' => 'JWT', 'alg' => $this->algorithm]
                |> (fn (array $h): string => json_encode($h))
                |> $this->base64UrlEncode(...);

        $payload['iat'] = time();
        $payload['exp'] = time() + $this->expiration;

        $payload = $payload
                |> (fn (array $p): string => json_encode($p))
                |> $this->base64UrlEncode(...);

        $signature = hash_hmac('sha256', "$header.$payload", $this->secret, true)
                |> $this->base64UrlEncode(...);

        return "$header.$payload.$signature";
    }

    /**
     * @return array<string, mixed>
     * @throws JwtException
     */
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
        return $data
                |> base64_encode(...)
                |> (fn($x) => strtr($x, '+/', '-_'))
                |> (fn($x) => rtrim($x, '='));
    }

    private function base64UrlDecode(string $data): string
    {
        return $data
                |> (fn (string $d): string => strtr($d, '-_', '+/'))
                |> base64_decode(...);
    }
}