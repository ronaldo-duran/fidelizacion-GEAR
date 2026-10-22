<?php

declare(strict_types=1);

namespace App\Jobs\Nocturno;

use App\Actions\Nocturno\ExpireLotes;
use App\Models\MovimientoPuntos;

/**
 * 1. Expiry: every customer with a lot whose validity ended before the
 * cutoff. Customers already expired find nothing left and insert nothing.
 */
class ExpirePuntos extends NightlyJob
{
    public function handle(ExpireLotes $expire): void
    {
        $corte = $this->corteDate();
        $ids = MovimientoPuntos::query()->toBase()
            ->where('puntos', '>', 0)
            ->where('vigencia_hasta', '<', $corte->toDateString())
            ->distinct()
            ->orderBy('cliente_id')
            ->pluck('cliente_id');

        $this->eachCliente(self::ids($ids), static fn (int $clienteId): array => $expire($clienteId, $corte));
    }
}
