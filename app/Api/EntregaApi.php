<?php

declare(strict_types=1);

namespace App\Api;

use App\Enums\TipoDocumento;

/**
 * Una entrega de beneficio pedida por la API (E9-01, operación 7): por el
 * código que muestra el cliente, o por su documento y el beneficio. La llave
 * de idempotencia es la referencia de la redención.
 */
final class EntregaApi
{
    public readonly ?string $codigo;

    public readonly ?TipoDocumento $tipoDocumento;

    public readonly ?string $documento;

    public readonly ?int $beneficioId;

    public readonly ?int $montoCuenta;

    public readonly ?string $soporte;

    public readonly string $llave;

    public function __construct(
        ?string $codigo,
        ?TipoDocumento $tipoDocumento,
        ?string $documento,
        ?int $beneficioId,
        ?int $montoCuenta,
        ?string $soporte,
        string $llave,
    ) {
        $this->codigo = $codigo;
        $this->tipoDocumento = $tipoDocumento;
        $this->documento = $documento;
        $this->beneficioId = $beneficioId;
        $this->montoCuenta = $montoCuenta;
        $this->soporte = $soporte;
        $this->llave = $llave;
    }

    public function porCodigo(): bool
    {
        return $this->codigo !== null;
    }
}
