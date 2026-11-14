<?php

declare(strict_types=1);

namespace App\Puntos;

/**
 * Una fila del historial (E8-05, regla 4): "El Rancho de Javi", "27 oct ·
 * Compra $85.000" y "+85". `suma` decide el color de los puntos.
 */
final class FilaHistorial
{
    public readonly string $titulo;

    public readonly string $linea;

    public readonly string $puntos;

    public readonly bool $suma;

    public function __construct(string $titulo, string $linea, string $puntos, bool $suma)
    {
        $this->titulo = $titulo;
        $this->linea = $linea;
        $this->puntos = $puntos;
        $this->suma = $suma;
    }
}
