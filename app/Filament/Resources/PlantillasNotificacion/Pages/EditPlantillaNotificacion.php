<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlantillasNotificacion\Pages;

use App\Actions\Notificaciones\UpdatePlantillaNotificacion;
use App\Filament\Resources\Concerns\VuelveALaLista;
use App\Filament\Resources\PlantillasNotificacion\PlantillaNotificacionResource;
use App\Models\PlantillaNotificacion;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Guarda con `UpdatePlantillaNotificacion`. Si una variable no está en la
 * lista blanca, el error aparece en su campo y no se guarda nada.
 */
class EditPlantillaNotificacion extends EditRecord
{
    use VuelveALaLista;

    protected static string $resource = PlantillaNotificacionResource::class;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();

        abort_unless($record instanceof PlantillaNotificacion && $user instanceof User, 403);

        try {
            return app(UpdatePlantillaNotificacion::class)(
                $user,
                $record,
                is_string($data['asunto'] ?? null) ? $data['asunto'] : '',
                is_string($data['cuerpo'] ?? null) ? $data['cuerpo'] : '',
                ($data['activa'] ?? false) === true,
            );
        } catch (ValidationException $exception) {
            $errores = [];

            foreach ($exception->errors() as $campo => $mensajes) {
                $errores['data.' . $campo] = $mensajes;
            }

            throw ValidationException::withMessages($errores);
        }
    }
}
