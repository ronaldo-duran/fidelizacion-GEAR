<?php

declare(strict_types=1);

namespace App\Puntos;

use App\Enums\TipoMovimiento;
use App\Models\MovimientoPuntos;

/**
 * Works out how many points are left in each lot of a customer. A lot is any
 * positive movement. Negative movements consume lots: a reversal takes its
 * own purchase, an expiry takes expired lots, and a negative adjustment takes
 * the lots that expire first (FIFO). Pure: no database access.
 */
class LoteCalculator
{
    /**
     * @param  iterable<MovimientoPuntos>  $movimientos  ordered by creation
     * @return array<int, int> remaining points per lot, keyed by movement id
     */
    public function remaining(iterable $movimientos): array
    {
        /** @var array<int, int> $remaining */
        $remaining = [];
        /** @var array<int, MovimientoPuntos> $lots */
        $lots = [];

        foreach ($movimientos as $movimiento) {
            if ($movimiento->puntos > 0) {
                $remaining[$movimiento->id] = $movimiento->puntos;
                $lots[$movimiento->id] = $movimiento;

                continue;
            }

            $pending = -$movimiento->puntos;

            foreach ($this->candidates($lots, $remaining, $movimiento) as $id) {
                $taken = min($pending, $remaining[$id]);
                $remaining[$id] -= $taken;
                $pending -= $taken;

                if ($pending === 0) {
                    break;
                }
            }
        }

        return $remaining;
    }

    /**
     * Remaining points of the lots that belong to one purchase.
     *
     * @param  iterable<MovimientoPuntos>  $movimientos  ordered by creation
     * @return array<int, int> remaining points per lot of that purchase
     */
    public function remainingForTransaccion(iterable $movimientos, int $transaccionId): array
    {
        $ofTransaccion = [];

        foreach ($movimientos as $movimiento) {
            if ($movimiento->puntos > 0 && $movimiento->transaccion_id === $transaccionId) {
                $ofTransaccion[$movimiento->id] = true;
            }
        }

        return array_intersect_key($this->remaining($movimientos), $ofTransaccion);
    }

    /**
     * Lot ids a negative movement may consume, in the order it consumes them.
     *
     * @param  array<int, MovimientoPuntos>  $lots
     * @param  array<int, int>  $remaining
     * @return list<int>
     */
    private function candidates(array $lots, array $remaining, MovimientoPuntos $negative): array
    {
        $open = array_filter($lots, static fn (MovimientoPuntos $lot): bool => $remaining[$lot->id] > 0);

        if ($negative->tipo === TipoMovimiento::Reversa) {
            $open = array_filter(
                $open,
                static fn (MovimientoPuntos $lot): bool => $lot->transaccion_id === $negative->transaccion_id,
            );
        }

        if ($negative->tipo === TipoMovimiento::Caducidad && $negative->created_at !== null) {
            $day = $negative->created_at->startOfDay();
            $expired = array_filter(
                $open,
                static fn (MovimientoPuntos $lot): bool => $lot->vigencia_hasta?->lt($day) === true,
            );
            $open = $expired === [] ? $open : $expired;
        }

        uasort($open, $this->expiresFirst(...));

        return array_keys($open);
    }

    /**
     * Sooner expiry first; lots without expiry last; ties by creation order.
     */
    private function expiresFirst(MovimientoPuntos $first, MovimientoPuntos $second): int
    {
        $firstExpiry = $first->vigencia_hasta;
        $secondExpiry = $second->vigencia_hasta;

        if ($firstExpiry !== null && $secondExpiry !== null && ! $firstExpiry->eq($secondExpiry)) {
            return $firstExpiry <=> $secondExpiry;
        }

        $byMissingExpiry = ($firstExpiry === null) <=> ($secondExpiry === null);

        return $byMissingExpiry !== 0 ? $byMissingExpiry : $first->id <=> $second->id;
    }
}
