<?php

declare(strict_types=1);

namespace App\Puntos;

use App\Models\MovimientoPuntos;

/**
 * Sign and required fields per movement type (E3-02 spec table). The model
 * checks it on every insert, so no path can write a malformed movement.
 */
class MovimientoShape
{
    /**
     * Facts each movement type must satisfy.
     */
    private const REQUIRED = [
        'acumulacion' => ['positive', 'rule', 'merchant', 'transaction'],
        'reversa' => ['negative', 'rule', 'merchant', 'transaction', 'reason_and_user'],
        'caducidad' => ['negative', 'rule_or_reason'],
        'ajuste_manual' => ['non_zero', 'no_rule', 'reason_and_user'],
        'promocion' => ['positive', 'rule'],
    ];

    /**
     * @return list<string> broken rules; empty when the movement is valid
     */
    public function errors(MovimientoPuntos $movimiento): array
    {
        $facts = $this->facts($movimiento);
        $errors = [];

        foreach (self::REQUIRED[$movimiento->tipo->value] as $fact) {
            if (! $facts[$fact]) {
                $errors[] = "A {$movimiento->tipo->value} movement requires: {$fact}.";
            }
        }

        if ($movimiento->puntos === 0) {
            $errors[] = 'Points can never be zero.';
        }

        return $errors;
    }

    /**
     * @return array<string, bool>
     */
    private function facts(MovimientoPuntos $movimiento): array
    {
        $hasRule = $movimiento->regla_id !== null && $movimiento->regla_version !== null;
        $hasReason = $movimiento->motivo !== null && trim($movimiento->motivo) !== '';

        return [
            'positive' => $movimiento->puntos > 0,
            'negative' => $movimiento->puntos < 0,
            'non_zero' => $movimiento->puntos !== 0,
            'rule' => $hasRule,
            'no_rule' => ! $hasRule,
            'rule_or_reason' => $hasRule || $hasReason,
            'merchant' => $movimiento->comercio_id !== null,
            'transaction' => $movimiento->transaccion_id !== null,
            'reason_and_user' => $hasReason && $movimiento->usuario_id !== null,
        ];
    }
}
