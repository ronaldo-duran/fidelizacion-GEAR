<?php

declare(strict_types=1);

namespace App\Filament\Resources\Comercios\Pages;

use App\Filament\Resources\Comercios\ComercioResource;
use App\Filament\Resources\Concerns\VuelveALaLista;
use Filament\Resources\Pages\CreateRecord;

class CreateComercio extends CreateRecord
{
    use VuelveALaLista;

    protected static string $resource = ComercioResource::class;
}
