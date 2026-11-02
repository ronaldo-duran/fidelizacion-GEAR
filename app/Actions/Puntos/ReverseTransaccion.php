<?php

declare(strict_types=1);

namespace App\Actions\Puntos;

use App\Enums\TipoMovimiento;
use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use App\Models\User;
use App\Puntos\Ledger;
use App\Puntos\LoteCalculator;
use App\Puntos\PlazoReversa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Voids the points of a purchase with negative `reversa` movements, one per
 * accrual lot, copying each lot's rule snapshot. The originals are never
 * touched. Each reversal is capped at what is left of its own lot, so it never
 * takes points that came from other purchases.
 */
class ReverseTransaccion
{
    private readonly Ledger $ledger;

    private readonly LoteCalculator $lotes;

    public function __construct(Ledger $ledger, LoteCalculator $lotes)
    {
        $this->ledger = $ledger;
        $this->lotes = $lotes;
    }

    /**
     * @return list<MovimientoPuntos> the reversals inserted; empty when the
     *                                purchase had no points left to reverse
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, int $transaccionId, string $motivo): array
    {
        $original = $this->firstAccrual($transaccionId);

        Gate::forUser($user)->authorize('reverse', $original);

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Escribe el motivo de la reversa.']);
        }

        $this->assertWithinWindow($original);

        return DB::transaction(function () use ($user, $original, $transaccionId, $motivo): array {
            $cliente = $this->ledger->lockCliente($original->cliente_id);
            $alreadyReversed = MovimientoPuntos::query()
                ->where('transaccion_id', $transaccionId)
                ->where('tipo', TipoMovimiento::Reversa->value)
                ->toBase()
                ->exists();

            if ($alreadyReversed) {
                throw ValidationException::withMessages(['transaccion' => 'Esta compra ya fue revertida.']);
            }

            return $this->reverseLots($cliente, $user, $transaccionId, $motivo);
        });
    }

    /**
     * @return list<MovimientoPuntos>
     */
    private function reverseLots(Cliente $cliente, User $user, int $transaccionId, string $motivo): array
    {
        $movimientos = $this->ledgerOf($cliente);
        $remaining = $this->lotes->remainingForTransaccion($movimientos, $transaccionId);
        $reversals = [];

        foreach ($movimientos as $lot) {
            $left = $remaining[$lot->id] ?? 0;

            if ($left === 0) {
                continue;
            }

            $note = $left < $lot->puntos ? " (topada: quedaban {$left} de {$lot->puntos} puntos)" : '';
            $reversals[] = $this->ledger->append($cliente, [
                'tipo' => TipoMovimiento::Reversa,
                'puntos' => -$left,
                'comercio_id' => $lot->comercio_id,
                'transaccion_id' => $transaccionId,
                'regla_id' => $lot->regla_id,
                'regla_version' => $lot->regla_version,
                'motivo' => $motivo . $note,
                'usuario_id' => $user->id,
            ]);
        }

        return $reversals;
    }

    /**
     * @throws ValidationException
     */
    private function firstAccrual(int $transaccionId): MovimientoPuntos
    {
        $query = MovimientoPuntos::query()
            ->where('transaccion_id', $transaccionId)
            ->where('tipo', TipoMovimiento::Acumulacion->value);
        $query->getQuery()->orderBy('id');
        $original = $query->first();

        if ($original === null) {
            throw ValidationException::withMessages(['transaccion' => 'Esa compra no tiene puntos registrados.']);
        }

        return $original;
    }

    /**
     * The window counts calendar days in America/Bogota, not instants.
     *
     * @throws ValidationException
     */
    private function assertWithinWindow(MovimientoPuntos $original): void
    {
        if (PlazoReversa::vencido($original->created_at ?? CarbonImmutable::now())) {
            $days = PlazoReversa::dias();

            throw ValidationException::withMessages([
                'transaccion' => "La compra tiene más de {$days} días y ya no se puede revertir.",
            ]);
        }
    }

    /**
     * @return Collection<int, MovimientoPuntos>
     */
    private function ledgerOf(Cliente $cliente): Collection
    {
        $query = MovimientoPuntos::query()->where('cliente_id', $cliente->id);
        $query->getQuery()->orderBy('created_at')->orderBy('id');

        return $query->get();
    }
}
