<?php

declare(strict_types=1);

namespace App\Filament\Resources\Misiones\Pages;

use App\Enums\TipoRegla;
use App\Filament\Resources\Misiones\MisionResource;
use App\Filament\Resources\Reglas\Pages\CreateRegla;

/**
 * Igual que crear una regla, con el tipo fijo en `mision`.
 */
class CreateMision extends CreateRegla
{
    protected static string $resource = MisionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'tipo' => TipoRegla::Mision->value];
    }
}
