<?php

declare(strict_types=1);

namespace App\Niveles;

use App\Enums\Nivel;
use App\Models\Cliente;
use Carbon\CarbonImmutable;

/**
 * Works out a customer's level sources on a given day. Pure: the caller
 * hands in the customer and the `niveles` rule definition.
 */
class NivelCalculator
{
    /**
     * @param  array<array-key, mixed>  $definicionNiveles  definition of the `niveles` rule in force
     */
    public function fuentes(Cliente $cliente, array $definicionNiveles, CarbonImmutable $day): FuentesNivel
    {
        return new FuentesNivel(
            $this->porPuntos($cliente->saldo_puntos, $definicionNiveles),
            $this->vigente($cliente->nivel_por_membresia, $cliente->membresia_vigente_hasta, $day),
            $this->vigente($cliente->nivel_por_evento, $cliente->evento_vigente_hasta, $day),
            $cliente->evento_vigente_hasta,
        );
    }

    /**
     * The highest level whose threshold the balance reaches.
     *
     * @param  array<array-key, mixed>  $definicionNiveles
     */
    public function porPuntos(int $saldo, array $definicionNiveles): Nivel
    {
        $umbrales = is_array($definicionNiveles['umbrales'] ?? null) ? $definicionNiveles['umbrales'] : [];
        $nivel = Nivel::Bronce;

        foreach ($umbrales as $umbral) {
            $candidate = is_array($umbral) && is_string($umbral['nivel'] ?? null)
                ? Nivel::tryFrom($umbral['nivel'])
                : null;
            $puntos = is_array($umbral) ? ($umbral['umbral_puntos'] ?? null) : null;

            if ($candidate !== null && is_int($puntos) && $saldo >= $puntos) {
                $nivel = Nivel::highest($nivel, $candidate);
            }
        }

        return $nivel;
    }

    /**
     * A level that lasts until a date counts that whole day.
     */
    private function vigente(?Nivel $nivel, ?CarbonImmutable $hasta, CarbonImmutable $day): ?Nivel
    {
        return $hasta !== null && $hasta->gte($day) ? $nivel : null;
    }
}
