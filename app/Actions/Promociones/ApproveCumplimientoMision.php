<?php

declare(strict_types=1);

namespace App\Actions\Promociones;

use App\Actions\Niveles\RaiseNivelEfectivo;
use App\Actions\Puntos\RecordPromocion;
use App\Enums\EstadoCumplimiento;
use App\Enums\MotivoCambioNivel;
use App\Models\CumplimientoMision;
use App\Models\MovimientoPuntos;
use App\Models\PromocionOtorgada;
use App\Models\Regla;
use App\Models\User;
use App\Promociones\PendienteDeRevision;
use App\Puntos\Ledger;
use App\Puntos\VigenciaPromocion;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Un usuario del comercio dueño con `aprueba_misiones` aprueba un
 * cumplimiento pendiente y se abonan los puntos (E3-06).
 *
 * Abona la versión de la misión con que el cliente lo envió, aunque haya
 * cambiado o se haya desactivado (regla dura 3.3). Los puntos entran al libro
 * como un movimiento `promocion` y pueden subir el nivel en la misma petición
 * (regla dura 3.6). El máximo por cliente lo garantiza la restricción única
 * de `promociones_otorgadas` (regla dura 3.7): cada abono ocupa una ocurrencia
 * entre 1 y el máximo, y dos aprobaciones simultáneas no pueden tomar la misma.
 */
class ApproveCumplimientoMision
{
    private readonly Ledger $ledger;

    private readonly RecordPromocion $record;

    private readonly RaiseNivelEfectivo $raise;

    private readonly VigenciaPromocion $vigencia;

    public function __construct(
        Ledger $ledger,
        RecordPromocion $record,
        RaiseNivelEfectivo $raise,
        VigenciaPromocion $vigencia,
    ) {
        $this->ledger = $ledger;
        $this->record = $record;
        $this->raise = $raise;
        $this->vigencia = $vigencia;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, int $cumplimientoId): MovimientoPuntos
    {
        $cumplimiento = CumplimientoMision::query()->findOrFail($cumplimientoId);

        Gate::forUser($user)->authorize('review', $cumplimiento);

        $now = CarbonImmutable::now();
        $vigenciaHasta = $this->vigencia->hasta($now);

        if ($vigenciaHasta === null) {
            throw ValidationException::withMessages([
                'cumplimiento' => 'No hay una regla de acumulación vigente para saber cuándo vencen los puntos.',
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $cumplimiento, $now, $vigenciaHasta): MovimientoPuntos {
                $this->ledger->lockCliente($cumplimiento->cliente_id);
                $cumplimiento = PendienteDeRevision::lock($cumplimiento->id);
                $mision = Regla::query()->findOrFail($cumplimiento->regla_id);
                $ocurrencia = $this->siguienteOcurrencia($cumplimiento, $mision);
                $puntos = $mision->definicion['puntos'];

                $movimiento = ($this->record)(
                    $cumplimiento->cliente_id,
                    $cumplimiento->comercio_id,
                    is_int($puntos) ? $puntos : 0,
                    $mision,
                    $vigenciaHasta,
                );

                PromocionOtorgada::query()->create([
                    'cliente_id' => $cumplimiento->cliente_id,
                    'codigo' => $mision->codigo,
                    'ocurrencia' => $ocurrencia,
                    'movimiento_id' => $movimiento->id,
                ]);

                $cumplimiento->estado = EstadoCumplimiento::Aprobado;
                $cumplimiento->revisado_por = $user->id;
                $cumplimiento->revisado_at = $now;
                $cumplimiento->save();

                ($this->raise)($cumplimiento->cliente_id, MotivoCambioNivel::AscensoPromocion);

                return $movimiento;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'cumplimiento' => 'Esta misión ya se abonó a este cliente. Recarga la lista.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function siguienteOcurrencia(CumplimientoMision $cumplimiento, Regla $mision): int
    {
        $ocurrencia = PromocionOtorgada::query()
            ->where('cliente_id', $cumplimiento->cliente_id)
            ->where('codigo', $mision->codigo)
            ->toBase()
            ->count() + 1;
        $maximo = $mision->definicion['max_por_cliente'];

        if (! is_int($maximo) || $ocurrencia > $maximo) {
            throw ValidationException::withMessages([
                'cumplimiento' => 'El cliente ya completó esta misión todas las veces que se puede. Recházalo.',
            ]);
        }

        return $ocurrencia;
    }
}
