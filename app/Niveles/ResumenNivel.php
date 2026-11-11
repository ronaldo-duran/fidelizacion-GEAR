<?php

declare(strict_types=1);

namespace App\Niveles;

use App\Enums\Nivel;
use App\Enums\OrigenNivel;
use App\Models\Cliente;
use Carbon\CarbonImmutable;

/**
 * Arma el bloque del nivel del inicio (E8-03, reglas 1 a 5) con lo que ya
 * calcularon E3-03 y la regla `niveles` vigente. Solo lee: nunca recalcula
 * ni guarda el nivel efectivo.
 */
class ResumenNivel
{
    private readonly NivelEfectivo $niveles;

    private readonly NivelCalculator $calculator;

    public function __construct(NivelEfectivo $niveles, NivelCalculator $calculator)
    {
        $this->niveles = $niveles;
        $this->calculator = $calculator;
    }

    public function para(Cliente $cliente, ?CarbonImmutable $dia = null): NivelDelCliente
    {
        $dia = ($dia ?? CarbonImmutable::now())->startOfDay();
        $definicion = $this->niveles->definicionNiveles();
        $nivel = $cliente->nivel_efectivo;
        $siguiente = $this->calculator->siguiente($nivel, $cliente->saldo_puntos, $definicion);
        $porcentaje = $siguiente === null
            ? null
            : $this->porcentaje($cliente->saldo_puntos, $nivel, $siguiente['nivel'], $definicion);

        return new NivelDelCliente(
            $nivel,
            self::primerNombre($cliente->nombre_completo),
            $siguiente,
            $porcentaje,
            self::origen($cliente, $dia),
        );
    }

    /**
     * Avance del umbral del nivel actual al del siguiente, redondeado hacia
     * abajo. Con el saldo por debajo del umbral actual (nivel por membresía o
     * evento) queda en 0.
     *
     * @param  array<array-key, mixed>  $definicion
     */
    private function porcentaje(int $saldo, Nivel $actual, Nivel $siguiente, array $definicion): int
    {
        $desde = $this->calculator->umbral($actual, $definicion) ?? 0;
        $hasta = $this->calculator->umbral($siguiente, $definicion) ?? $desde;

        if ($hasta <= $desde) {
            return 0;
        }

        return max(0, min(100, intdiv(($saldo - $desde) * 100, $hasta - $desde)));
    }

    /**
     * La membresía o el evento que dan el nivel efectivo cuando los puntos no
     * alcanzan. Si los dos lo dan, el que dura más.
     *
     * @return array{0: OrigenNivel, 1: CarbonImmutable|null}
     */
    private static function origen(Cliente $cliente, CarbonImmutable $dia): array
    {
        $nivel = $cliente->nivel_efectivo;

        if (! $nivel->isAbove($cliente->nivel_por_puntos)) {
            return [OrigenNivel::Puntos, null];
        }

        $candidatos = [
            [OrigenNivel::Membresia, $cliente->nivel_por_membresia, $cliente->membresia_vigente_hasta],
            [OrigenNivel::Evento, $cliente->nivel_por_evento, $cliente->evento_vigente_hasta],
        ];
        $mejor = [OrigenNivel::Puntos, null];

        foreach ($candidatos as [$origen, $nivelFuente, $hasta]) {
            $daElNivel = $nivelFuente === $nivel && $hasta !== null && $hasta->gte($dia);

            if ($daElNivel && ($mejor[1] === null || $hasta->gt($mejor[1]))) {
                $mejor = [$origen, $hasta];
            }
        }

        return $mejor;
    }

    private static function primerNombre(?string $nombreCompleto): ?string
    {
        $palabras = preg_split('/\s+/u', trim((string) $nombreCompleto), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($palabras) && $palabras !== [] ? $palabras[0] : null;
    }
}
