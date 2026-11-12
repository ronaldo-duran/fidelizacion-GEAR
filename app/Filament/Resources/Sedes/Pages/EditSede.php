<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sedes\Pages;

use App\Filament\Resources\Concerns\VuelveALaLista;
use App\Filament\Resources\Sedes\SedeResource;
use Filament\Resources\Pages\EditRecord;

class EditSede extends EditRecord
{
    use VuelveALaLista;

    protected static string $resource = SedeResource::class;

    /**
     * Una sede no cambia de comercio (sus compras y redenciones ya quedaron
     * con ese comercio): el campo va bloqueado y aquí se descarta por si una
     * petición alterada lo manda.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['comercio_id']);

        return $data;
    }
}
