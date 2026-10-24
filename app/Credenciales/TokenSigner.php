<?php

declare(strict_types=1);

namespace App\Credenciales;

use RuntimeException;

/**
 * Firma y lee los tokens del QR: `base64url(payload) . "." . base64url(hmac)`,
 * con un HMAC-SHA256 con la llave de la aplicación. Un token cuya firma no
 * coincide nunca se lee.
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
     * El contenido de un token con firma válida, o null.
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
            throw new RuntimeException('Falta la llave de la aplicación para firmar las credenciales QR.');
        }

        return hash_hmac('sha256', 'credencial-qr|' . $body, $key, true);
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
