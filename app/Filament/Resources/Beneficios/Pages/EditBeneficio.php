<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficios\Pages;

use App\Filament\Resources\Beneficios\BeneficioResource;
use App\Filament\Resources\Concerns\VuelveALaLista;
use App\Models\Beneficio;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBeneficio extends EditRecord
{
    use GuardaBeneficio;
    use VuelveALaLista;

    protected static string $resource = BeneficioResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Beneficio, 404);

        return $this->guardar($data, $record);
    }

    /**
     * Borrar solo si no tiene redenciones (`BeneficioPolicy::delete`); si tiene, se desactiva.
     */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
