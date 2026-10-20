<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Identity document types. Many customers in Villa del Rosario and Cúcuta are
 * Venezuelan without a Colombian ID, so the number alone does not identify a
 * person: the customer is the pair (type, number). Screens always ask for the
 * type and suggest CC first, the most common one.
 */
enum TipoDocumento: string implements HasLabel
{
    case CC = 'CC';
    case CE = 'CE';
    case PPT = 'PPT';
    case PA = 'PA';
    case DEX = 'DEX';

    public function getLabel(): string
    {
        return match ($this) {
            self::CC => 'Cédula de ciudadanía',
            self::CE => 'Cédula de extranjería',
            self::PPT => 'Permiso por protección temporal',
            self::PA => 'Pasaporte',
            self::DEX => 'Documento extranjero (p. ej. cédula venezolana)',
        };
    }
}
