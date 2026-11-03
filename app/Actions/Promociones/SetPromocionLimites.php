<?php

declare(strict_types=1);

namespace App\Actions\Promociones;

use App\Models\Comercio;
use App\Models\ComercioPromocionLimite;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * El grupo define, por comercio, si puede tener misiones y cuántos puntos
 * como máximo da una misión (E3-06, regla 6). Bajar el tope no toca las
 * misiones ya guardadas: aplica a la siguiente versión que se active.
 */
class SetPromocionLimites
{
    /**
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag") `permiteMisiones` es un dato del límite, no un modo de ejecución.
     *
     * @throws ValidationException
     */
    public function __invoke(
        User $user,
        int $comercioId,
        bool $permiteMisiones,
        int $puntosMaxPorMision,
    ): ComercioPromocionLimite {
        Gate::forUser($user)->authorize('update', ComercioPromocionLimite::class);

        if ($puntosMaxPorMision < 0 || ($permiteMisiones && $puntosMaxPorMision < 1)) {
            throw ValidationException::withMessages([
                'puntos_max_por_mision' => 'Con misiones habilitadas, el máximo por misión debe ser al menos 1 punto.',
            ]);
        }

        Comercio::query()->findOrFail($comercioId);

        return ComercioPromocionLimite::query()->updateOrCreate(
            ['comercio_id' => $comercioId],
            ['permite_misiones' => $permiteMisiones, 'puntos_max_por_mision' => $puntosMaxPorMision],
        );
    }
}
