<?php

declare(strict_types=1);

namespace App\Credenciales;

use RuntimeException;

/**
 * Signs and reads QR tokens: `base64url(payload) . "." . base64url(hmac)`,
 * with an HMAC-SHA256 keyed by the application key. A token whose signature
 * does not match is never read.
 */
class TokenSigner
{
    /**
     * @param  array<string, int|string|null>  $payload
     */
    public function sign(array $payload): string
    {
        $body = self::encode(json_encode($payload, JSON_THROW_ON_ERROR));

        return $body . '.' . self::encode($this->mac($body));
    }

    /**
     * The payload of a token with a valid signature, or null.
     *
     * @return array<array-key, mixed>|null
     */
    public function read(string $token): ?array
    {
        $parts = explode('.', trim($token));

        if (count($parts) !== 2 || ! hash_equals(self::encode($this->mac($parts[0])), $parts[1])) {
            return null;
        }

        $json = base64_decode(strtr($parts[0], '-_', '+/'), true);
        $payload = is_string($json) ? json_decode($json, true) : null;

        return is_array($payload) ? $payload : null;
    }

    private function mac(string $body): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('The application key is required to sign QR credentials.');
        }

        return hash_hmac('sha256', 'credencial-qr|' . $body, $key, true);
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
