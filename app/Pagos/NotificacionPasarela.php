<?php

declare(strict_types=1);

namespace App\Pagos;

use App\Enums\EstadoPago;

/**
 * Una notificación de la pasarela con la firma ya verificada (E6-01).
 * `montoCop` es null si la pasarela informó un monto con fracción de peso;
 * `estado` es null si informó un estado que no conocemos.
 */
final class NotificacionPasarela
{
    public readonly ?string $referencia;

    public readonly ?string $idExterno;

    public readonly ?EstadoPago $estado;

    public readonly string $estadoInformado;

    public readonly ?int $montoCop;

    public readonly ?string $moneda;

    /** @var array<array-key, mixed> */
    public readonly array $payload;

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function __construct(
        ?string $referencia,
        ?string $idExterno,
        ?EstadoPago $estado,
        string $estadoInformado,
        ?int $montoCop,
        ?string $moneda,
        array $payload,
    ) {
        $this->referencia = $referencia;
        $this->idExterno = $idExterno;
        $this->estado = $estado;
        $this->estadoInformado = $estadoInformado;
        $this->montoCop = $montoCop;
        $this->moneda = $moneda;
        $this->payload = $payload;
    }
}
