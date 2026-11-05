<?php

declare(strict_types=1);

namespace App\Actions\Notificaciones;

use App\Models\PlantillaNotificacion;
use App\Models\User;
use App\Notificaciones\ValidadorPlantilla;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Guarda el asunto, el cuerpo y el estado de una plantilla (E7-01). Solo
 * con variables de la lista blanca del evento (regla dura 3.8); el siguiente
 * envío usa el texto nuevo. La auditoría guarda el antes y el después.
 */
class UpdatePlantillaNotificacion
{
    private readonly ValidadorPlantilla $validador;

    public function __construct(ValidadorPlantilla $validador)
    {
        $this->validador = $validador;
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(
        User $user,
        PlantillaNotificacion $plantilla,
        string $asunto,
        string $cuerpo,
        bool $activa,
    ): PlantillaNotificacion {
        Gate::forUser($user)->authorize('update', $plantilla);

        $errores = $this->validador->errores($plantilla->evento, $asunto, $cuerpo);

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        $plantilla->fill(['asunto' => trim($asunto), 'cuerpo' => trim($cuerpo), 'activa' => $activa])->save();

        return $plantilla;
    }
}
