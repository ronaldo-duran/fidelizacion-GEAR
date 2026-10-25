<?php

declare(strict_types=1);

namespace App\Tablero;

/**
 * Formatos del tablero (los del fixture): variación frente al mes anterior,
 * dinero en pesos enteros (compacto en millones desde 10 M) y cifras con
 * separador de miles.
 */
final class FormatoTablero
{
    private const MENOS = "\u{2212}";

    private const MILLONES_DESDE = 10_000_000;

    /**
     * "+8 %", "−3 %", "0 %"; si el mes anterior fue cero, la cifra absoluta
     * ("+12", "0").
     */
    public static function variacion(int $actual, int $anterior): string
    {
        if ($anterior === 0) {
            return self::conSigno($actual);
        }

        $porcentaje = (int) round(($actual - $anterior) * 100 / $anterior);

        return self::conSigno($porcentaje) . ' %';
    }

    /**
     * Sube, baja o se mantiene, para el color de la variación.
     */
    public static function tendencia(int $actual, int $anterior): int
    {
        return $actual <=> $anterior;
    }

    /**
     * "$19,5 M" desde 10 millones; "$3.180.000" por debajo.
     */
    public static function dinero(int $pesos): string
    {
        if ($pesos < self::MILLONES_DESDE) {
            return '$' . self::cifra($pesos);
        }

        $millones = number_format($pesos / 1_000_000, 1, ',', '.');

        return '$' . preg_replace('/,0$/', '', $millones) . ' M';
    }

    public static function cifra(int $valor): string
    {
        return number_format($valor, 0, ',', '.');
    }

    public static function sinRegistros(?int $dias): string
    {
        return $dias === null ? 'Sin registros' : "Sin registros hace {$dias} días";
    }

    private static function conSigno(int $valor): string
    {
        return match (true) {
            $valor > 0 => '+' . self::cifra($valor),
            $valor < 0 => self::MENOS . self::cifra(-$valor),
            default => '0',
        };
    }
}
