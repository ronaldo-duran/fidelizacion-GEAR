<?php

declare(strict_types=1);

namespace App\Filament\Resources\Misiones\Pages;

use App\Filament\Resources\Misiones\MisionResource;
use App\Filament\Resources\Reglas\Pages\ListReglas;

class ListMisiones extends ListReglas
{
    protected static string $resource = MisionResource::class;
}
