<?php

declare(strict_types=1);

namespace App\Puntos;

/**
 * Result of recalculating a customer's balance from the ledger.
 */
final class SaldoCheck
{
    public readonly int $calculated;

    public readonly int $materialized;

    public function __construct(int $calculated, int $materialized)
    {
        $this->calculated = $calculated;
        $this->materialized = $materialized;
    }

    public function matches(): bool
    {
        return $this->calculated === $this->materialized;
    }
}
