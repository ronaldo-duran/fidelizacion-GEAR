<?php

declare(strict_types=1);

namespace App\Compras;

use App\Enums\Nivel;
use App\Models\Transaccion;
use App\Reglas\PuntosDesglose;

/**
 * Outcome of registering a purchase: the purchase, how its points were
 * reached, the level before and after, and whether it was a retry that
 * returned an existing purchase.
 */
final class RegistroCompra
{
    public readonly Transaccion $transaccion;

    public readonly PuntosDesglose $puntos;

    public readonly ?Nivel $nivelAntes;

    public readonly ?Nivel $nivelDespues;

    public readonly bool $repetida;

    public function __construct(
        Transaccion $transaccion,
        PuntosDesglose $puntos,
        ?Nivel $nivelAntes,
        ?Nivel $nivelDespues,
        bool $repetida,
    ) {
        $this->transaccion = $transaccion;
        $this->puntos = $puntos;
        $this->nivelAntes = $nivelAntes;
        $this->nivelDespues = $nivelDespues;
        $this->repetida = $repetida;
    }

    public function subioDeNivel(): bool
    {
        return $this->nivelAntes !== null
            && $this->nivelDespues !== null
            && $this->nivelDespues->isAbove($this->nivelAntes);
    }
}
