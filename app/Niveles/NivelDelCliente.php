<?php

declare(strict_types=1);

namespace App\Niveles;

use App\Enums\Nivel;
use App\Enums\OrigenNivel;
use Carbon\CarbonImmutable;

/**
 * Lo que pinta el bloque del nivel en el inicio del cliente (E8-03): el
 * nivel efectivo, cuánto falta para el siguiente y de dónde sale el nivel.
 * Sin nivel siguiente (Diamante), `siguiente`, `faltan` y `porcentaje` son
 * null. `hasta` solo viene cuando el origen es una membresía o un evento.
 */
final class NivelDelCliente
{
    public readonly Nivel $nivel;

    public readonly ?string $primerNombre;

    public readonly ?Nivel $siguiente;

    public readonly ?int $faltan;

    public readonly ?int $porcentaje;

    public readonly OrigenNivel $origen;

    public readonly ?CarbonImmutable $hasta;

    /**
     * @param  array{nivel: Nivel, faltan: int}|null  $siguiente
     * @param  array{0: OrigenNivel, 1: CarbonImmutable|null}  $origen
     */
    public function __construct(Nivel $nivel, ?string $primerNombre, ?array $siguiente, ?int $porcentaje, array $origen)
    {
        $this->nivel = $nivel;
        $this->primerNombre = $primerNombre;
        $this->siguiente = $siguiente['nivel'] ?? null;
        $this->faltan = $siguiente['faltan'] ?? null;
        $this->porcentaje = $porcentaje;
        [$this->origen, $this->hasta] = $origen;
    }
}
