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

    /**
     * "3 de septiembre", sin depender del idioma instalado en el servidor.
     */
    public static function fecha(DateTimeInterface $dia): string
    {
        return $dia->format('j') . ' de ' . self::mes($dia);
    }

    /**
     * "septiembre": el nombre del mes en minúscula.
     */
    public static function mes(DateTimeInterface $dia): string
    {
        $meses = [
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
        ];

        return $meses[(int) $dia->format('n') - 1];
    }

    /**
     * "27 sep": día y mes abreviado, para las filas del historial.
     */
    public static function fechaCorta(DateTimeInterface $dia): string
    {
        return $dia->format('j') . ' ' . mb_substr(self::mes($dia), 0, 3);
    }

    /**
     * "4 de noviembre de 2027": para fechas que pueden caer en otro año, como
     * el fin de una membresía anual.
     */
    public static function fechaConAnio(DateTimeInterface $dia): string
    {
        return self::fecha($dia) . ' de ' . $dia->format('Y');
    }

    public static function hora(DateTimeInterface $momento): string
    {
        $hora = $momento->format('g');
        $minuto = $momento->format('i');
        $meridiano = (int) $momento->format('G') < 12 ? 'a. m.' : 'p. m.';

        return "{$hora}:{$minuto} {$meridiano}";
    }
}
