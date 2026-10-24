<?php

declare(strict_types=1);

namespace App\Verificacion;

use App\Enums\PropositoVerificacion;
use RuntimeException;

/**
 * Hashes verification codes with an HMAC keyed by the application key, bound
 * to the phone and the purpose, and normalizes Colombian mobiles. The code
 * is never stored nor logged.
 */
class CodigoHasher
{
    public function hash(PropositoVerificacion $proposito, string $celular, string $codigo): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('The application key is required to hash verification codes.');
        }

        return hash_hmac('sha256', "{$proposito->value}|{$celular}|{$codigo}", $key);
    }

    /**
     * Ten digits, without spaces, signs nor the 57 country code.
     */
    public static function normalizeCelular(string $celular): string
    {
        $digits = preg_replace('/\D/', '', $celular) ?? '';

        return strlen($digits) === 12 && str_starts_with($digits, '57') ? substr($digits, 2) : $digits;
    }
}
