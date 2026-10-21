<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventosCalificados\Pages;

use App\Filament\Resources\EventosCalificados\EventoCalificadoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventosCalificados extends ListRecords
{
    protected static string $resource = EventoCalificadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Registrar evento'),
        ];
    }
}
