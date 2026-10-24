<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the cashier gets back when validating a QR credential or its short
 * code. Only `Valido` carries customer data.
 */
enum ResultadoCredencial: string
{
    case Valido = 'valido';
    case Vencido = 'vencido';
    case Usado = 'usado';
    case Invalido = 'invalido';
    case Bloqueado = 'bloqueado';

    public function mensaje(): string
    {
        return match ($this) {
            self::Valido => 'Código válido.',
            self::Vencido => 'El código venció. Pídele al cliente que lo actualice.',
            self::Usado => 'Este código ya se usó.',
            self::Invalido => 'El código no es válido.',
            self::Bloqueado => 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.',
        };
    }
}
