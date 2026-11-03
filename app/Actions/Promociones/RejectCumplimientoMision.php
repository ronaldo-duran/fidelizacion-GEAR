<?php

declare(strict_types=1);

namespace App\Actions\Promociones;

use App\Enums\EstadoCumplimiento;
use App\Models\CumplimientoMision;
use App\Models\User;
use App\Promociones\PendienteDeRevision;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Un usuario del comercio dueño con `aprueba_misiones` rechaza un
 * cumplimiento pendiente (E3-06). No abona nada; quedan el motivo y quién lo
 * rechazó.
 */
class RejectCumplimientoMision
{
    private const MOTIVO_MAX = 255;

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, int $cumplimientoId, string $motivo): CumplimientoMision
    {
        $cumplimiento = CumplimientoMision::query()->findOrFail($cumplimientoId);

        Gate::forUser($user)->authorize('review', $cumplimiento);

        $motivo = trim($motivo);

        if ($motivo === '' || mb_strlen($motivo) > self::MOTIVO_MAX) {
            throw ValidationException::withMessages([
                'motivo' => $motivo === ''
                    ? 'Escribe por qué se rechaza: el cliente lo verá.'
                    : 'El motivo no puede pasar de ' . self::MOTIVO_MAX . ' caracteres.',
            ]);
        }

        return DB::transaction(static function () use ($user, $cumplimiento, $motivo): CumplimientoMision {
            $cumplimiento = PendienteDeRevision::lock($cumplimiento->id);
            $cumplimiento->estado = EstadoCumplimiento::Rechazado;
            $cumplimiento->motivo_rechazo = $motivo;
            $cumplimiento->revisado_por = $user->id;
            $cumplimiento->revisado_at = CarbonImmutable::now();
            $cumplimiento->save();

            return $cumplimiento;
        });
    }
}
