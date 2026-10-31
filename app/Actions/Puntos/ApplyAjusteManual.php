<?php

declare(strict_types=1);

namespace App\Actions\Puntos;

use App\Actions\Niveles\RaiseNivelEfectivo;
use App\Enums\MotivoCambioNivel;
use App\Models\MovimientoPuntos;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El ajuste manual del panel (E10-06): inserta el movimiento por el libro y,
 * si suma, evalúa el ascenso en la misma transacción (regla dura 3.6). Un
 * ajuste negativo baja el saldo de una vez, pero el nivel solo lo reevalúa el
 * proceso nocturno.
 */
class ApplyAjusteManual
{
    private readonly RecordAjusteManual $record;

    private readonly RaiseNivelEfectivo $raise;

    public function __construct(RecordAjusteManual $record, RaiseNivelEfectivo $raise)
    {
        $this->record = $record;
        $this->raise = $raise;
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, int $clienteId, int $puntos, string $motivo): MovimientoPuntos
    {
        return DB::transaction(function () use ($user, $clienteId, $puntos, $motivo): MovimientoPuntos {
            $movimiento = ($this->record)($user, $clienteId, $puntos, $motivo);

            if ($puntos > 0) {
                ($this->raise)($clienteId, MotivoCambioNivel::AscensoAjuste);
            }

            return $movimiento;
        });
    }
}
