<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reglas\Pages;

use App\Filament\Resources\Reglas\ReglaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReglas extends ListRecords
{
    protected static string $resource = ReglaResource::class;

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
