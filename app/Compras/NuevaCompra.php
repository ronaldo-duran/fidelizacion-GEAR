<?php

declare(strict_types=1);

namespace App\Compras;

use App\Enums\CanalCompra;
use App\Enums\TipoDocumento;
use App\Models\Cliente;

/**
 * A purchase to register, as the cashier, the bulk upload or the API send it.
 * The document and the reference are normalized here so lookups and the
 * unique constraint match on every engine (hard rules 3.10 and 3.13).
 */
final class NuevaCompra
{
    public readonly int $comercioId;

    public readonly ?int $sedeId;

    public readonly TipoDocumento $tipoDocumento;

    public readonly string $documento;

    public readonly int $monto;

    public readonly string $referenciaExterna;

    public readonly CanalCompra $canal;

    public readonly ?string $idempotencyKey;

    public function __construct(
        int $comercioId,
        ?int $sedeId,
        TipoDocumento $tipoDocumento,
        string $documento,
        int $monto,
        string $referenciaExterna,
        CanalCompra $canal,
        ?string $idempotencyKey = null,
    ) {
        $this->comercioId = $comercioId;
        $this->sedeId = $sedeId;
        $this->tipoDocumento = $tipoDocumento;
        $this->documento = Cliente::normalizeDocumento($documento);
        $this->monto = $monto;
        $this->referenciaExterna = mb_strtolower(str_replace(' ', '', trim($referenciaExterna)));
        $this->canal = $canal;
        $this->idempotencyKey = $idempotencyKey;
    }
}
