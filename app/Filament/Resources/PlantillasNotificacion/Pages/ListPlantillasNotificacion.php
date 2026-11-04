<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlantillasNotificacion\Pages;

use App\Filament\Resources\PlantillasNotificacion\PlantillaNotificacionResource;
use Filament\Resources\Pages\ListRecords;

class ListPlantillasNotificacion extends ListRecords
{
    protected static string $resource = PlantillaNotificacionResource::class;
}
