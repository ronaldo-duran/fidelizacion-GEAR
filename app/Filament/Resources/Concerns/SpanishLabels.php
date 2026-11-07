<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concerns;

use Illuminate\Support\Str;

/**
 * Títulos y migas del panel en español. Filament pone en mayúscula cada
 * palabra de la etiqueta («Plantillas De Avisos»), como en inglés; en
 * español solo va la primera letra («Plantillas de avisos»). Es un trait y
 * no una clase base para no alargar la jerarquía de los recursos.
 */
trait SpanishLabels
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
