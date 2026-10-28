<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficios\Pages;

use App\Filament\Resources\Beneficios\BeneficioResource;
use App\Filament\Resources\Concerns\VuelveALaLista;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBeneficio extends CreateRecord
{
    use GuardaBeneficio;
    use VuelveALaLista;

    protected static string $resource = BeneficioResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->guardar($data);
    }
}
