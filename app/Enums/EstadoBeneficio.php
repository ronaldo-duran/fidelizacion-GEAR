<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * El estado de un beneficio para un cliente en una fecha (E5-01, regla 9).
 * Lo consumen el inicio y el catálogo del cliente (E8-03, E8-04) y la
 * redención (E5-02).
 */
enum EstadoBeneficio: string
{
    case Disponible = 'disponible';
    case Bloqueado = 'bloqueado';
    case NoDisponibleHoy = 'no_disponible_hoy';
    case Usado = 'usado';
    case SinCupos = 'sin_cupos';
}
