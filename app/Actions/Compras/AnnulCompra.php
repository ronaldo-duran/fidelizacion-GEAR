<?php

declare(strict_types=1);

namespace App\Actions\Compras;

use App\Actions\Puntos\ReverseTransaccion;
use App\Enums\EstadoTransaccion;
use App\Enums\TipoMovimiento;
use App\Models\MovimientoPuntos;
use App\Models\Transaccion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Annuls a purchase (the "Anular" button of the cashier confirmation). A
 * purchase with points is reversed through the ledger (E3-02), within its
 * window and with its authorization; a pending purchase, or one that earned
 * nothing, has nothing to reverse and is only marked annulled.
 */
class AnnulCompra
{
    private readonly ReverseTransaccion $reverse;

    public function __construct(ReverseTransaccion $reverse)
    {
        $this->reverse = $reverse;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, int $transaccionId, string $motivo): Transaccion
    {
        $transaccion = Transaccion::query()->findOrFail($transaccionId);

        Gate::forUser($user)->authorize('annul', $transaccion);

        if ($transaccion->estado === EstadoTransaccion::Anulada) {
            throw ValidationException::withMessages(['transaccion' => 'Esta compra ya fue anulada.']);
        }

        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'Escribe el motivo de la anulación.']);
        }

        return DB::transaction(function () use ($user, $transaccion, $motivo): Transaccion {
            if ($this->hasPoints($transaccion)) {
                ($this->reverse)($user, $transaccion->id, $motivo);
            }

            $transaccion->forceFill(['estado' => EstadoTransaccion::Anulada])->save();

            return $transaccion;
        });
    }

    private function hasPoints(Transaccion $transaccion): bool
    {
        return MovimientoPuntos::query()->toBase()
            ->where('transaccion_id', $transaccion->id)
            ->where('tipo', TipoMovimiento::Acumulacion->value)
            ->exists();
    }
}
