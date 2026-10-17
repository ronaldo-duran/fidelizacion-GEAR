<?php

declare(strict_types=1);

namespace App\Niveles;

use App\Enums\MotivoCambioNivel;
use App\Enums\Nivel;
use App\Enums\TipoRegla;
use App\Models\CambioNivel;
use App\Models\Cliente;
use App\Models\Regla;
use App\Puntos\Ledger;
use Carbon\CarbonImmutable;

/**
 * Shared steps of the level actions. Callers open the transaction and lock
 * the customer row first, like the points ledger, so level changes of one
 * customer are serialized.
 */
class NivelEfectivo
{
    private readonly Ledger $ledger;

    private readonly NivelCalculator $calculator;

    public function __construct(Ledger $ledger, NivelCalculator $calculator)
    {
        $this->ledger = $ledger;
        $this->calculator = $calculator;
    }

    public function lockCliente(int $clienteId): Cliente
    {
        return $this->ledger->lockCliente($clienteId);
    }

    /**
     * Definition of the `niveles` rule in force: thresholds, protection and
     * notices. Without one, every customer stays in Bronce by points.
     *
     * @return array<array-key, mixed>
     */
    public function definicionNiveles(): array
    {
        $query = Regla::inForceAt(CarbonImmutable::now());
        $query->getQuery()->where('tipo', TipoRegla::Niveles->value)->orderByDesc('vigente_desde');

        return $query->first()->definicion ?? [];
    }

    /**
     * Recomputes the points source, stores it on the customer (not saved
     * yet) and returns the level the three valid sources give.
     *
     * @param  array<array-key, mixed>  $definicionNiveles
     */
    public function refreshFuentes(Cliente $cliente, array $definicionNiveles, CarbonImmutable $day): Nivel
    {
        $fuentes = $this->calculator->fuentes($cliente, $definicionNiveles, $day);

        $cliente->forceFill(['nivel_por_puntos' => $fuentes->porPuntos]);

        return $fuentes->nivel();
    }

    /**
     * Sets the new effective level (not saved yet) and records the change.
     */
    public function change(Cliente $cliente, Nivel $nuevo, MotivoCambioNivel $motivo): void
    {
        CambioNivel::query()->create([
            'cliente_id' => $cliente->id,
            'nivel_anterior' => $cliente->nivel_efectivo,
            'nivel_nuevo' => $nuevo,
            'motivo' => $motivo,
        ]);

        $cliente->forceFill([
            'nivel_efectivo' => $nuevo,
            'nivel_efectivo_desde' => CarbonImmutable::now(),
        ]);
    }
}
