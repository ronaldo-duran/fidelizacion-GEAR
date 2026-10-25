<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sedes\Pages;

use App\Filament\Resources\Concerns\VuelveALaLista;
use App\Filament\Resources\Sedes\SedeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSede extends CreateRecord
{
    use VuelveALaLista;

    protected static string $resource = SedeResource::class;
}
