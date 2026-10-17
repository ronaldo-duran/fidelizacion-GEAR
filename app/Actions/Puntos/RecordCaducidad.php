<?php

declare(strict_types=1);

namespace App\Actions\Puntos;

use App\Enums\TipoMovimiento;
use App\Models\MovimientoPuntos;
use App\Models\Regla;
use App\Puntos\Ledger;
use Illuminate\Support\Facades\DB;

/**
 * Removes expired points with a negative `caducidad` movement. The nightly job
 * (E3-05) decides how many points expire; a lot that came from a manual
 * adjustment expires without a rule and with a reason.
 */
class RecordCaducidad
{
    private readonly Ledger $ledger;

    public function __construct(Ledger $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * @param  int  $puntos  points to expire, as a positive number
     */
    public function __invoke(int $clienteId, int $puntos, ?Regla $regla, ?string $motivo = null): MovimientoPuntos
    {
        return DB::transaction(fn (): MovimientoPuntos => $this->ledger->append(
            $this->ledger->lockCliente($clienteId),
            [
                'tipo' => TipoMovimiento::Caducidad,
                'puntos' => -$puntos,
                'regla_id' => $regla?->id,
                'regla_version' => $regla?->version,
                'motivo' => $motivo,
            ],
        ));
    }
}
