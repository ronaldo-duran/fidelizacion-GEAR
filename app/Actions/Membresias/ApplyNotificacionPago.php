<?php

declare(strict_types=1);

namespace App\Actions\Membresias;

use App\Enums\EstadoPago;
use App\Enums\ResultadoNotificacionPago;
use App\Models\NotificacionPago;
use App\Models\PagoMembresia;
use App\Pagos\NotificacionPasarela;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Aplica una notificación de la pasarela con la firma ya verificada (E6-01,
 * reglas 4 a 9) y la deja registrada en `notificaciones_pago`.
 *
 * El pago se lee con la fila bloqueada: un webhook repetido o en paralelo
 * espera al primero, lo encuentra ya aprobado y no hace nada. Solo un pago
 * pendiente cambia; lo que llegue después de un estado final se registra
 * para revisión y no deshace nada.
 */
class ApplyNotificacionPago
{
    private readonly GrantMembresia $grant;

    public function __construct(GrantMembresia $grant)
    {
        $this->grant = $grant;
    }

    public function __invoke(string $pasarela, NotificacionPasarela $notificacion): ResultadoNotificacionPago
    {
        return DB::transaction(function () use ($pasarela, $notificacion): ResultadoNotificacionPago {
            $pago = $this->lockPago($pasarela, $notificacion->referencia);
            $resultado = $pago === null
                ? ResultadoNotificacionPago::ReferenciaDesconocida
                : $this->apply($pago, $notificacion);

            NotificacionPago::query()->create([
                'pasarela' => $pasarela,
                'referencia' => $notificacion->referencia,
                'id_externo' => $notificacion->idExterno,
                'estado_informado' => $notificacion->estadoInformado,
                'firma_valida' => true,
                'resultado' => $resultado,
                'payload' => $notificacion->payload,
            ]);

            return $resultado;
        });
    }

    private function apply(PagoMembresia $pago, NotificacionPasarela $notificacion): ResultadoNotificacionPago
    {
        if ($notificacion->estado === null || $notificacion->estado === $pago->estado) {
            return ResultadoNotificacionPago::Repetido;
        }

        if ($pago->estado !== EstadoPago::Pendiente || $notificacion->estado === EstadoPago::Pendiente) {
            return ResultadoNotificacionPago::TransicionInvalida;
        }

        if ($notificacion->estado !== EstadoPago::Aprobado) {
            $pago->forceFill(['estado' => $notificacion->estado, 'id_externo' => $notificacion->idExterno])->save();

            return ResultadoNotificacionPago::Rechazado;
        }

        if ($notificacion->montoCop !== $pago->monto_cop || $notificacion->moneda !== 'COP') {
            return ResultadoNotificacionPago::MontoDistinto;
        }

        $pago->forceFill([
            'estado' => EstadoPago::Aprobado,
            'id_externo' => $notificacion->idExterno,
            'aprobado_at' => CarbonImmutable::now(),
        ])->save();
        ($this->grant)($pago);

        return ResultadoNotificacionPago::Aplicado;
    }

    private function lockPago(string $pasarela, ?string $referencia): ?PagoMembresia
    {
        if ($referencia === null) {
            return null;
        }

        $query = PagoMembresia::query();
        $query->getQuery()->where('pasarela', $pasarela)->where('referencia', $referencia)->lockForUpdate();

        return $query->first();
    }
}
