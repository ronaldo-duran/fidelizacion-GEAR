<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanesMembresia\Pages;

use App\Filament\Resources\PlanesMembresia\PlanMembresiaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlanesMembresia extends ListRecords
{
    protected static string $resource = PlanMembresiaResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
