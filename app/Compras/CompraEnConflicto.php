<?php

declare(strict_types=1);

namespace App\Compras;

use App\Models\Transaccion;
use RuntimeException;

/**
 * The reference of a purchase was already used by the same merchant with
 * different data. The API answers it with 409 (E9-01).
 */
final class CompraEnConflicto extends RuntimeException
{
    public readonly Transaccion $existente;

    public function __construct(Transaccion $existente)
    {
        parent::__construct('Esa referencia ya se usó en este comercio con otros datos.');
        $this->existente = $existente;
    }
}
