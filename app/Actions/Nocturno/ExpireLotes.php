<?php

declare(strict_types=1);

namespace App\Actions\Nocturno;

use App\Actions\Puntos\RecordCaducidad;
use App\Models\MovimientoPuntos;
use App\Models\Regla;
use App\Puntos\Ledger;
use App\Puntos\LoteCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Expires what is left of a customer's lots whose validity ended before the
 * cutoff date: one `caducidad` per rule and version, with its snapshot
 * (hard rule 3.3); lots from a manual adjustment expire without a rule and
 * with a reason. It never deletes (hard rule 3.4).
 *
 * Idempotent by recalculation: each expiry already consumed what it expired,
 * so a second run, or the run after a missed night, only finds what is still
 * pending.
 */
class ExpireLotes
{
    public const MOTIVO_AJUSTE = 'Vencimiento de puntos de un ajuste manual';

    private readonly Ledger $ledger;

    private readonly LoteCalculator $lotes;

    private readonly RecordCaducidad $recordCaducidad;

    public function __construct(Ledger $ledger, LoteCalculator $lotes, RecordCaducidad $recordCaducidad)
    {
        $this->ledger = $ledger;
        $this->lotes = $lotes;
        $this->recordCaducidad = $recordCaducidad;
    }

    /**
     * @return list<MovimientoPuntos> the expiry movements inserted
     */
    public function __invoke(int $clienteId, CarbonImmutable $corte): array
    {
        return DB::transaction(function () use ($clienteId, $corte): array {
            $this->ledger->lockCliente($clienteId);
            $inserted = [];

            foreach ($this->expiredByRegla($clienteId, $corte->startOfDay()) as $group) {
                $regla = $group['regla_id'] === null ? null : Regla::query()->findOrFail($group['regla_id']);
                $inserted[] = ($this->recordCaducidad)(
                    $clienteId,
                    $group['puntos'],
                    $regla,
                    $regla === null ? self::MOTIVO_AJUSTE : null,
                );
            }

            return $inserted;
        });
    }

    /**
     * Remaining points of the expired lots, grouped by rule version.
     *
     * @return list<array{regla_id: int|null, puntos: int}>
     */
    private function expiredByRegla(int $clienteId, CarbonImmutable $corte): array
    {
        $movimientos = MovimientoPuntos::query()->where('cliente_id', $clienteId)->oldest('id')->get();
        $remaining = $this->lotes->remaining($movimientos);
        $groups = [];

        foreach ($movimientos as $lote) {
            $left = $remaining[$lote->id] ?? 0;

            if ($left <= 0 || $lote->vigencia_hasta === null || ! $lote->vigencia_hasta->lt($corte)) {
                continue;
            }

            $key = ($lote->regla_id ?? 0) . ':' . ($lote->regla_version ?? 0);
            $groups[$key] ??= ['regla_id' => $lote->regla_id, 'puntos' => 0];
            $groups[$key]['puntos'] += $left;
        }

        return array_values($groups);
    }
}
