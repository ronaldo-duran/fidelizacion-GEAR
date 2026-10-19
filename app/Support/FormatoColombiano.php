<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;

/**
 * Formato colombiano para la interfaz: pesos sin centavos, puntos y hora de 12 horas.
 *
 * El peso colombiano no tiene centavos (CLAUDE.md 3.9), por eso los montos y los
 * puntos son enteros. La hora sigue el estilo "7:42 p. m." de las pantallas aprobadas.
 */
final class FormatoColombiano
{
    public static function pesos(int $monto): string
    {
        return '$' . number_format($monto, 0, ',', '.');
    }

    public static function puntos(int $puntos): string
    {
        return number_format($puntos, 0, ',', '.') . ' pts';
    }

    public static function hora(DateTimeInterface $momento): string
    {
        $hora = $momento->format('g');
        $minuto = $momento->format('i');
        $meridiano = (int) $momento->format('G') < 12 ? 'a. m.' : 'p. m.';

        return "{$hora}:{$minuto} {$meridiano}";
    }
}
