<?php

declare(strict_types=1);

namespace App\Filament\Resources\Misiones\Pages;

use App\Filament\Resources\Misiones\MisionResource;
use App\Filament\Resources\Reglas\Pages\NewReglaVersion;

/**
 * Guardar crea `version + 1` por `VersionRegla`, que valida los límites del
 * comercio si la versión queda activa (regla dura 3.2).
 */
class NewMisionVersion extends NewReglaVersion
{
    protected static string $resource = MisionResource::class;
}
