<?php

declare(strict_types=1);

namespace App\Jobs\Nocturno;

use App\Actions\Puntos\RecalculateSaldo;
use App\Models\Cliente;
use Illuminate\Support\Facades\Log;

/**
 * 4. Balance check: recalculates every customer's balance from the ledger.
 * A mismatch is logged as an alert and never fixed automatically.
 */
class VerifySaldos extends NightlyJob
{
    public function handle(RecalculateSaldo $recalculate): void
    {
        $ids = Cliente::query()->toBase()->orderBy('id')->pluck('id');

        $this->eachCliente(self::ids($ids), function (int $clienteId) use ($recalculate): void {
            $check = $recalculate($clienteId);

            if (! $check->matches()) {
                Log::channel('alertas')->warning('Customer balance does not match the ledger.', [
                    'corte' => $this->corte,
                    'cliente_id' => $clienteId,
                    'saldo_libro' => $check->calculated,
                    'saldo_materializado' => $check->materialized,
                ]);
            }
        });
    }
}
