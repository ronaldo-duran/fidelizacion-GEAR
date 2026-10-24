<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lo que recibe el cajero al validar una credencial QR o su código corto.
 * Solo `Valido` trae datos del cliente.
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
