<?php

declare(strict_types=1);

namespace App\Promociones;

use App\Enums\EstadoCumplimiento;
use App\Models\CumplimientoMision;
use Illuminate\Validation\ValidationException;

/**
 * Un cumplimiento se revisa una sola vez (E3-06): aprobar y rechazar lo leen
 * bloqueado dentro de su transacción y exigen que siga pendiente, así dos
 * revisiones simultáneas no lo cierran dos veces.
 */
final class PendienteDeRevision
{
    /**
     * @throws ValidationException
     */
    public static function lock(int $cumplimientoId): CumplimientoMision
    {
        $query = CumplimientoMision::query()->whereKey($cumplimientoId);
        $query->getQuery()->lockForUpdate();
        $cumplimiento = $query->firstOrFail();

        if ($cumplimiento->estado !== EstadoCumplimiento::Pendiente) {
            throw ValidationException::withMessages([
                'cumplimiento' => 'Este cumplimiento ya fue revisado. Recarga la lista.',
            ]);
        }

        return $cumplimiento;
    }
}
