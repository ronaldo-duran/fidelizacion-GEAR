<?php

declare(strict_types=1);

namespace App\Actions\Puntos;

use App\Enums\TipoMovimiento;
use App\Models\MovimientoPuntos;
use App\Models\Regla;
use App\Puntos\Ledger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Credits the points of a purchase with the snapshot of the rule that
 * produced them (hard rule 3.3). A system operation: the caller (E4-01)
 * authorizes it.
 */
class RecordAcumulacion
{
    private readonly Ledger $ledger;

    public function __construct(Ledger $ledger)
    {
        $this->ledger = $ledger;
    }

    public function __invoke(
        int $clienteId,
        int $comercioId,
        int $transaccionId,
        int $puntos,
        Regla $regla,
        CarbonInterface $vigenciaHasta,
    ): MovimientoPuntos {
        return DB::transaction(fn (): MovimientoPuntos => $this->ledger->append(
            $this->ledger->lockCliente($clienteId),
            [
                'tipo' => TipoMovimiento::Acumulacion,
                'puntos' => $puntos,
                'comercio_id' => $comercioId,
                'transaccion_id' => $transaccionId,
                'regla_id' => $regla->id,
                'regla_version' => $regla->version,
                'vigencia_hasta' => $vigenciaHasta,
            ],
        ));
    }
}
