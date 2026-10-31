<?php

declare(strict_types=1);

namespace App\Beneficios;

/**
 * Lo que el cajero confirma al entregar un beneficio (E5-02): a quién, cuál,
 * con qué código (null si fue por documento), el monto de la cuenta para un
 * porcentaje, el soporte y la referencia del intento.
 */
final class EntregaBeneficio
{
    public readonly int $clienteId;

    public readonly int $beneficioId;

    public readonly ?int $credencialId;

    public readonly ?int $montoCuenta;

    public readonly ?string $soporte;

    public readonly string $referencia;

    public function __construct(
        int $clienteId,
        int $beneficioId,
        ?int $credencialId,
        ?int $montoCuenta,
        ?string $soporte,
        string $referencia,
    ) {
        $this->clienteId = $clienteId;
        $this->beneficioId = $beneficioId;
        $this->credencialId = $credencialId;
        $this->montoCuenta = $montoCuenta;
        $this->soporte = $soporte;
        $this->referencia = $referencia;
    }
}
