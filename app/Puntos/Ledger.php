<?php

declare(strict_types=1);

namespace App\Puntos;

use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of the points ledger. Callers open the database transaction
 * and lock the customer first, so movements of one customer are serialized
 * and the balance is updated together with the movement (hard rule 3.4).
 */
class Ledger
{
    public function lockCliente(int $clienteId): Cliente
    {
        $query = Cliente::query()->whereKey($clienteId);
        $query->getQuery()->lockForUpdate();

        return $query->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function append(Cliente $cliente, array $attributes): MovimientoPuntos
    {
        $puntos = $attributes['puntos'] ?? 0;
        $puntos = is_int($puntos) ? $puntos : 0;

        if ($cliente->saldo_puntos + $puntos < 0) {
            throw ValidationException::withMessages([
                'puntos' => 'El movimiento dejaría el saldo de puntos por debajo de cero.',
            ]);
        }

        $movimiento = MovimientoPuntos::query()->create([...$attributes, 'cliente_id' => $cliente->id]);

        Cliente::query()->whereKey($cliente->id)->increment('saldo_puntos', $puntos);
        $cliente->saldo_puntos += $puntos;
        $cliente->syncOriginalAttribute('saldo_puntos');

        return $movimiento;
    }
}
