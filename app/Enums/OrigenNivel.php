<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * De cuál de las tres fuentes sale el nivel efectivo que ve el cliente
 * (E8-03, regla 5).
 */
enum OrigenNivel: string
{
    case Puntos = 'puntos';
    case Membresia = 'membresia';
    case Evento = 'evento';
}
