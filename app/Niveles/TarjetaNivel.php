<?php

declare(strict_types=1);

namespace App\Niveles;

use App\Enums\Nivel;

/**
 * Una tarjeta de la pantalla de niveles (E8-03, regla 8). `umbral` es null en
 * Bronce ("Al registrarte"); `faltan` solo viene en el nivel siguiente.
 */
final class TarjetaNivel
{
    public readonly Nivel $nivel;

    public readonly ?int $umbral;

    public readonly int $beneficios;

    public readonly bool $actual;

    public readonly ?int $faltan;

    public function __construct(Nivel $nivel, ?int $umbral, int $beneficios, bool $actual, ?int $faltan)
    {
        $this->nivel = $nivel;
        $this->umbral = $umbral;
        $this->beneficios = $beneficios;
        $this->actual = $actual;
        $this->faltan = $faltan;
    }
}
