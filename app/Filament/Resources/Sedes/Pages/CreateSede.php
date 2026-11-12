<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sedes\Pages;

use App\Filament\Resources\Concerns\VuelveALaLista;
use App\Filament\Resources\Sedes\SedeResource;
use App\Models\Sede;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Gate;

class CreateSede extends CreateRecord
{
    use VuelveALaLista;

    protected static string $resource = SedeResource::class;

    /**
     * El comercio de la sede se autoriza en el servidor: el formulario solo
     * ofrece los del alcance, pero una petición alterada podría mandar otro.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $comercioId = $data['comercio_id'] ?? null;

        Gate::authorize('save', [Sede::class, is_numeric($comercioId) ? (int) $comercioId : 0]);

        return $data;
    }
}
