<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reglas\Pages;

use App\Actions\Reglas\CreateRegla as CreateReglaAction;
use App\Enums\TipoRegla;
use App\Filament\Resources\Reglas\Pages\Concerns\SavesThroughReglaActions;
use App\Filament\Resources\Reglas\ReglaResource;
use App\Filament\Resources\Reglas\Schemas\DefinicionFormMapper;
use App\Models\Regla;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRegla extends CreateRecord
{
    use SavesThroughReglaActions;

    protected static string $resource = ReglaResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $tipo = TipoRegla::from(is_string($data['tipo'] ?? null) ? $data['tipo'] : '');
        $codigo = is_string($data['codigo'] ?? null) ? $data['codigo'] : '';

        return $this->saveThroughActions(function (User $user) use ($data, $tipo, $codigo): Regla {
            [$definicion, $source] = DefinicionFormMapper::fromForm($tipo, $data);

            return app(CreateReglaAction::class)(
                $user,
                $codigo,
                $tipo,
                $definicion,
                $source,
                $this->parseVigenteDesde($data['vigente_desde'] ?? null),
            );
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('view', ['record' => $this->getRecord()]);
    }
}
