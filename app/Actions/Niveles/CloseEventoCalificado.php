<?php

declare(strict_types=1);

namespace App\Actions\Niveles;

use App\Models\EventoCalificado;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Closes a qualifying event early (a child leaves the school): the end date
 * moves to the closing date, with a reason and the user, and the audit keeps
 * the before and after. It never lowers the level here: the nightly
 * evaluation does it (hard rule 3.6).
 */
class CloseEventoCalificado
{
    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, int $eventoId, CarbonInterface $fechaCierre, string $motivo): EventoCalificado
    {
        $evento = EventoCalificado::query()->findOrFail($eventoId);

        Gate::forUser($user)->authorize('close', $evento);

        $motivo = trim($motivo);
        $fecha = CarbonImmutable::instance($fechaCierre)->startOfDay();

        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo_cierre' => 'Escribe el motivo del cierre.']);
        }

        if ($evento->motivo_cierre !== null) {
            throw ValidationException::withMessages(['motivo_cierre' => 'Este evento ya fue cerrado.']);
        }

        if ($fecha->lt($evento->vigente_desde) || $fecha->gt($evento->vigente_hasta)) {
            throw ValidationException::withMessages([
                'fecha_cierre' => 'La fecha de cierre debe estar dentro de la vigencia del evento.',
            ]);
        }

        $evento->forceFill([
            'vigente_hasta' => $fecha,
            'motivo_cierre' => $motivo,
            'cerrado_por' => $user->id,
        ])->save();

        return $evento;
    }
}
