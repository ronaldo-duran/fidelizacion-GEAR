<?php

declare(strict_types=1);

namespace App\Verificacion;

use App\Enums\PropositoVerificacion;
use RuntimeException;

/**
 * Calcula la huella de los códigos de verificación con un HMAC con la llave de
 * la aplicación, atada al celular y al propósito, y normaliza celulares
 * colombianos. El código nunca se guarda ni se escribe en un log.
 */
class CodigoHasher
{
    public function hash(PropositoVerificacion $proposito, string $celular, string $codigo): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Falta la llave de la aplicación para la huella de los códigos.');
        }

        return hash_hmac('sha256', "{$proposito->value}|{$celular}|{$codigo}", $key);
    }

    /**
     * Diez dígitos, sin espacios, signos ni el indicativo 57.
     */
    public static function normalizeCelular(string $celular): string
    {
        $digits = preg_replace('/\D/', '', $celular) ?? '';

        return strlen($digits) === 12 && str_starts_with($digits, '57') ? substr($digits, 2) : $digits;
    }

    /**
     * Un celular colombiano normalizado: diez dígitos que empiezan por 3.
     */
    public static function isCelular(string $celular): bool
    {
        return preg_match('/^3\d{9}$/', $celular) === 1;
    }
}
