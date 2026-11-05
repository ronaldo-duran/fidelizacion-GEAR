<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Illuminate\Support\Str;

/**
 * Base de los recursos del panel. Filament pone en mayúscula cada palabra de
 * la etiqueta en títulos y migas («Plantillas De Avisos»), como en inglés;
 * en español solo va la primera letra («Plantillas de avisos»).
 */
abstract class BaseResource extends Resource
{
    public static function getTitleCaseModelLabel(): string
    {
        return Str::ucfirst(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }
}
