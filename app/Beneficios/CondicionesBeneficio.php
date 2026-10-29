<?php

declare(strict_types=1);

namespace App\Beneficios;

use Carbon\CarbonImmutable;

/**
 * Evalúa las condiciones de uso de un beneficio (E5-01) en un día de
 * America/Bogota. Solo conoce las claves de la lista blanca
 * (`es_cumpleanos`, `dia_semana`, `fin_de_semana`); nunca evalúa un texto.
 * Pura: recibe la fecha de nacimiento y el día.
 */
final class CondicionesBeneficio
{
    /** Fin de semana: viernes, sábado y domingo (decisión 5 de la spec E5-01). */
    public const FIN_DE_SEMANA = ['viernes', 'sabado', 'domingo'];

    private const DIAS = [1 => 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    /** Con un año de búsqueda se encuentra cualquier día de la semana o cumpleaños. */
    private const DIAS_DE_BUSQUEDA = 366;

    /**
     * @param  array<string, mixed>  $condiciones
     */
    public static function cumple(array $condiciones, ?CarbonImmutable $nacimiento, CarbonImmutable $dia): bool
    {
        $nombreDia = self::DIAS[$dia->dayOfWeekIso];
        $dias = $condiciones['dia_semana'] ?? null;

        return (($condiciones['es_cumpleanos'] ?? false) !== true || self::esCumpleanos($nacimiento, $dia))
            && (! is_array($dias) || in_array($nombreDia, $dias, true))
            && (($condiciones['fin_de_semana'] ?? false) !== true || in_array($nombreDia, self::FIN_DE_SEMANA, true));
    }

    /**
     * El próximo día, después de hoy, en que se cumplen las condiciones; null
     * si no se cumplen en el año siguiente (p. ej. sin fecha de nacimiento).
     *
     * @param  array<string, mixed>  $condiciones
     */
    public static function proximaFecha(
        array $condiciones,
        ?CarbonImmutable $nacimiento,
        CarbonImmutable $dia,
    ): ?CarbonImmutable {
        for ($i = 1; $i <= self::DIAS_DE_BUSQUEDA; $i++) {
            $candidato = $dia->addDays($i);

            if (self::cumple($condiciones, $nacimiento, $candidato)) {
                return $candidato;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $condiciones
     */
    public static function faltaFechaDeNacimiento(array $condiciones, ?CarbonImmutable $nacimiento): bool
    {
        return ($condiciones['es_cumpleanos'] ?? false) === true && $nacimiento === null;
    }

    /**
     * El día y el mes del nacimiento; quien nació un 29 de febrero lo celebra
     * el 28 en los años no bisiestos.
     */
    private static function esCumpleanos(?CarbonImmutable $nacimiento, CarbonImmutable $dia): bool
    {
        if ($nacimiento === null) {
            return false;
        }

        $diaDelMes = $nacimiento->month === 2 && $nacimiento->day === 29 && ! $dia->isLeapYear()
            ? 28
            : $nacimiento->day;

        return $dia->month === $nacimiento->month && $dia->day === $diaDelMes;
    }
}
