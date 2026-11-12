<?php

declare(strict_types=1);

namespace App\Filament\Resources\Comercios\Pages;

use App\Actions\Promociones\SetPromocionLimites;
use App\Filament\Resources\Comercios\ComercioResource;
use App\Filament\Resources\Comercios\Schemas\ComercioForm;
use App\Filament\Resources\Concerns\VuelveALaLista;
use App\Models\Comercio;
use App\Models\ComercioPromocionLimite;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Los límites de promociones viven en su propia tabla y los guarda
 * `SetPromocionLimites`, que solo deja al grupo. La sección no llega en el
 * formulario de un administrador de comercio.
 */
class EditComercio extends EditRecord
{
    use VuelveALaLista;

    protected static string $resource = ComercioResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $limite = ComercioPromocionLimite::query()->where('comercio_id', $this->getRecord()->getKey())->first();

        return [...$data, 'promocion' => [
            'permite_misiones' => $limite->permite_misiones ?? false,
            'puntos_max_por_mision' => $limite->puntos_max_por_mision ?? 0,
        ]];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Comercio, 404);

        $promocion = $data['promocion'] ?? null;
        unset($data['promocion']);

        // La categoría, el NIT y el estado son del grupo: bloqueados en el formulario y descartados aquí.
        if (! Gate::allows('updateDatosDelGrupo', Comercio::class)) {
            $data = array_diff_key($data, array_flip(ComercioForm::DATOS_DEL_GRUPO));
        }

        return DB::transaction(static function () use ($record, $data, $promocion): Comercio {
            $record->update($data);
            $user = Auth::user();

            if (is_array($promocion) && $user instanceof User) {
                $puntos = $promocion['puntos_max_por_mision'] ?? 0;

                app(SetPromocionLimites::class)(
                    $user,
                    $record->id,
                    ($promocion['permite_misiones'] ?? false) === true,
                    is_numeric($puntos) ? (int) $puntos : 0,
                );
            }

            return $record;
        });
    }
}
