<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reglas\Pages;

use App\Filament\Resources\Reglas\ReglaResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewRegla extends ViewRecord
{
    protected static string $resource = ReglaResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Nueva versión')
                ->icon(Heroicon::OutlinedDocumentDuplicate),
        ];
    }
}
