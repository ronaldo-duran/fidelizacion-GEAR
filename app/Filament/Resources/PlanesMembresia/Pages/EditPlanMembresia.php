<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanesMembresia\Pages;

use App\Filament\Resources\Concerns\VuelveALaLista;
use App\Filament\Resources\PlanesMembresia\PlanMembresiaResource;
use Filament\Resources\Pages\EditRecord;

class EditPlanMembresia extends EditRecord
{
    use VuelveALaLista;

    protected static string $resource = PlanMembresiaResource::class;
}
