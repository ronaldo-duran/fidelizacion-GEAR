<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficios\Pages;

use App\Filament\Resources\Beneficios\BeneficioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBeneficios extends ListRecords
{
    protected static string $resource = BeneficioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo beneficio'),
        ];
    }
}
