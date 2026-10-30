<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lo que ve el cajero al validar o entregar un beneficio (E5-02). Solo
 * `Valido` permite confirmar la entrega; `Usado` va a la pantalla YA USADO
 * (1l) y el resto a NO VÁLIDO.
 */
enum ResultadoBeneficio: string
{
    case Valido = 'valido';
    case Usado = 'usado';
    case Vencido = 'vencido';
    case Invalido = 'invalido';
    case Bloqueado = 'bloqueado';
    case SinCupos = 'sin_cupos';
    case NoDisponibleHoy = 'no_disponible_hoy';
    case NivelInsuficiente = 'nivel_insuficiente';
    case NoDisponible = 'no_disponible';

    public static function fromCredencial(ResultadoCredencial $resultado): self
    {
        return match ($resultado) {
            ResultadoCredencial::Valido => self::Valido,
            ResultadoCredencial::Usado => self::Usado,
            ResultadoCredencial::Vencido => self::Vencido,
            ResultadoCredencial::Invalido => self::Invalido,
            ResultadoCredencial::Bloqueado => self::Bloqueado,
        };
    }

    public function mensaje(): string
    {
        return match ($this) {
            self::Valido => 'Beneficio válido.',
            self::Usado => 'Este beneficio ya se usó.',
            self::Vencido => 'El código venció. Pídele al cliente que lo actualice y escanéalo otra vez.',
            self::Invalido => 'El código no es válido. Pídele al cliente que lo muestre otra vez.',
            self::Bloqueado => 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.',
            self::SinCupos => 'Este beneficio ya no tiene cupos.',
            self::NoDisponibleHoy => 'Este beneficio no está disponible hoy.',
            self::NivelInsuficiente => 'El nivel del cliente no alcanza para este beneficio.',
            self::NoDisponible => 'Este beneficio ya no está disponible.',
        };
    }
}
