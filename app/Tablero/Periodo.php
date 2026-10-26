<?php

declare(strict_types=1);

namespace App\Tablero;

use Carbon\CarbonImmutable;

/**
 * Un mes calendario del tablero, en la zona de la aplicación (America/Bogota,
 * regla dura 3.9), con el mes anterior para comparar.
 */
final readonly class Periodo
{
    private const MESES = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    public CarbonImmutable $inicio;

    public CarbonImmutable $fin;

    private function __construct(CarbonImmutable $inicio)
    {
        $this->inicio = $inicio->startOfMonth();
        $this->fin = $inicio->endOfMonth();
    }

    /**
     * Un mes `AAAA-MM`; uno inválido es el mes en curso.
     */
    public static function desde(?string $mes): self
    {
        $valido = is_string($mes) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) === 1;
        $inicio = $valido ? CarbonImmutable::createFromFormat('!Y-m', $mes) : null;

        return new self($inicio ?? CarbonImmutable::now());
    }

    public static function actual(): self
    {
        return new self(CarbonImmutable::now());
    }

    public function anterior(): self
    {
        return new self($this->inicio->subMonthNoOverflow());
    }

    public function clave(): string
    {
        return $this->inicio->format('Y-m');
    }

    /**
     * "Septiembre 2026 · vs. agosto".
     */
    public function etiqueta(): string
    {
        return ucfirst($this->mes()) . ' ' . $this->inicio->year . ' · vs. ' . $this->anterior()->mes();
    }

    public function mes(): string
    {
        return self::MESES[$this->inicio->month];
    }

    /**
     * Los meses que se pueden elegir, del más reciente al más viejo.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        $cuantos = config('tablero.selectable_months');
        $periodo = self::actual();
        $opciones = [];

        for ($i = 0; $i < (is_int($cuantos) ? $cuantos : 1); $i++) {
            $opciones[$periodo->clave()] = $periodo->etiqueta();
            $periodo = $periodo->anterior();
        }

        return $opciones;
    }
}
