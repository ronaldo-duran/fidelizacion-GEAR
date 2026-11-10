<?php

declare(strict_types=1);

namespace App\Beneficios;

/**
 * Una fila del catálogo del cliente (E8-04, regla 3): el beneficio con su
 * estado y el texto que va debajo del nombre. `nivel` y `faltan` solo los
 * tiene un bloqueado; `enlace` dice si la fila lleva a su detalle.
 */
final class FilaCatalogo
{
    public readonly BeneficioParaCliente $item;

    public readonly string $meta;

    public function __construct(BeneficioParaCliente $item, string $meta)
    {
        $this->item = $item;
        $this->meta = $meta;
    }

    public function beneficioId(): int
    {
        return $this->item->beneficio->id;
    }

    public function comercio(): string
    {
        return $this->item->beneficio->comercio->razon_social ?? '';
    }

    public function nombre(): string
    {
        return $this->item->beneficio->nombre;
    }
}
