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
 * Credits the points of a promotion (registration bonus or mission, E3-06)
 * with its rule snapshot. The merchant is null for the registration bonus.
 */
class RecordPromocion
{
    private readonly Ledger $ledger;

    public function __construct(Ledger $ledger)
    {
        $this->ledger = $ledger;
    }

    public function __invoke(
        int $clienteId,
        ?int $comercioId,
        int $puntos,
        Regla $regla,
        CarbonInterface $vigenciaHasta,
    ): MovimientoPuntos {
        return DB::transaction(fn (): MovimientoPuntos => $this->ledger->append(
            $this->ledger->lockCliente($clienteId),
            [
                'tipo' => TipoMovimiento::Promocion,
                'puntos' => $puntos,
                'comercio_id' => $comercioId,
                'regla_id' => $regla->id,
                'regla_version' => $regla->version,
                'vigencia_hasta' => $vigenciaHasta,
            ],
        ));
    }
}
