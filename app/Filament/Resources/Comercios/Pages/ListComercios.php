<?php

declare(strict_types=1);

namespace App\Filament\Resources\Comercios\Pages;

use App\Filament\Resources\Comercios\ComercioResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComercios extends ListRecords
{
    protected static string $resource = ComercioResource::class;

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
