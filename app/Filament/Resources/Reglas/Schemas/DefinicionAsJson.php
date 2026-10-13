<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reglas\Schemas;

use App\Models\Regla;

/**
 * A version's definition as readable, escaped JSON, for the detail page and
 * the version history.
 */
class DefinicionAsJson
{
    public static function html(Regla $regla): string
    {
        $json = json_encode($regla->definicion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return '<pre>' . e($json) . '</pre>';
    }
}
