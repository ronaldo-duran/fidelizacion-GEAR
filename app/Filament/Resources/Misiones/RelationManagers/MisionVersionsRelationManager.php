<?php

declare(strict_types=1);

namespace App\Filament\Resources\Misiones\RelationManagers;

use App\Filament\Resources\Reglas\RelationManagers\VersionsRelationManager;

/**
 * El historial de solo lectura de una misión. La página que lo muestra ya
 * exige poder guardar versiones de esa misión (`MisionResource::canView`),
 * así que el administrador de su comercio también lo ve; `ReglaPolicy::view`
 * solo deja al grupo.
 */
class MisionVersionsRelationManager extends VersionsRelationManager
{
    protected static bool $shouldSkipAuthorization = true;
}
