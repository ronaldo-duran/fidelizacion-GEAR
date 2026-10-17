<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Events that grant a level by themselves (issue #9). The level each one
 * gives lives in `config/niveles.php`.
 */
enum TipoEventoCalificado: string implements HasLabel
{
    case CompraInmueble = 'compra_inmueble';
    case MatriculaColegio = 'matricula_colegio';

    public function getLabel(): string
    {
        return match ($this) {
            self::CompraInmueble => 'Compra de inmueble',
            self::MatriculaColegio => 'Matrícula en el colegio',
        };
    }
}
