<?php

declare(strict_types=1);

namespace App\Actions\Puntos;

use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use App\Puntos\SaldoCheck;

/**
 * Recalculates a customer's balance from scratch and compares it with the
 * materialized one. It never fixes anything; the nightly check (E3-05)
 * raises an alert on a mismatch.
 */
class RecalculateSaldo
{
    public function __invoke(int $clienteId): SaldoCheck
    {
        $cliente = Cliente::query()->findOrFail($clienteId);
        $sum = MovimientoPuntos::query()->where('cliente_id', $clienteId)->toBase()->sum('puntos');

        return new SaldoCheck(is_numeric($sum) ? (int) $sum : 0, $cliente->saldo_puntos);
    }
}
