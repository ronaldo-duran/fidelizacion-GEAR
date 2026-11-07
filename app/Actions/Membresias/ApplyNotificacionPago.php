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
 * espera al primero, lo encuentra ya aprobado y no hace nada. Aprobado es el
 * único estado final; lo que llegue después se registra para revisión y no
 * deshace nada. Un rechazo no es final: Wompi deja reintentar con la misma
 * referencia.
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
        $estado = $notificacion->estado;

        // Un estado que no conocemos, o un id de transacción que ya es de otro pago, van a revisión.
        if ($estado === null || $this->idDeOtroPago($pago, $notificacion)) {
            return ResultadoNotificacionPago::TransicionInvalida;
        }

        if ($pago->estado === EstadoPago::Aprobado) {
            return $this->sobreAprobado($pago, $notificacion);
        }

        if ($estado === $pago->estado || $estado === EstadoPago::Pendiente) {
            return ResultadoNotificacionPago::Repetido;
        }

        if ($estado !== EstadoPago::Aprobado) {
            return $this->reject($pago, $notificacion);
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

    /**
     * Un pago aprobado no se deshace solo (regla 8). La misma transacción
     * otra vez es un reintento del webhook; cualquier otra cosa (un rechazo
     * tardío o un segundo cobro aprobado de la misma referencia) queda para
     * revisión, porque puede ser dinero que el cliente pagó.
     */
    private function sobreAprobado(PagoMembresia $pago, NotificacionPasarela $notificacion): ResultadoNotificacionPago
    {
        $mismaTransaccion = $notificacion->idExterno === null || $notificacion->idExterno === $pago->id_externo;

        return $notificacion->estado === EstadoPago::Aprobado && $mismaTransaccion
            ? ResultadoNotificacionPago::Repetido
            : ResultadoNotificacionPago::TransicionInvalida;
    }

    /**
     * Rechazo o anulación de un pago pendiente. Uno que ya estaba rechazado o
     * anulado no cambia: el cliente puede reintentar en la pasarela con la
     * misma referencia, y si ese intento se aprueba, el pago se aplica.
     */
    private function reject(PagoMembresia $pago, NotificacionPasarela $notificacion): ResultadoNotificacionPago
    {
        if ($pago->estado !== EstadoPago::Pendiente) {
            return ResultadoNotificacionPago::Repetido;
        }

        $pago->forceFill(['estado' => $notificacion->estado, 'id_externo' => $notificacion->idExterno])->save();

        return ResultadoNotificacionPago::Rechazado;
    }

    /**
     * El id de la transacción ya está en otro pago: guardarlo chocaría con la
     * restricción única `(pasarela, id_externo)` y el webhook respondería 500
     * sin dejar registro.
     */
    private function idDeOtroPago(PagoMembresia $pago, NotificacionPasarela $notificacion): bool
    {
        if ($notificacion->idExterno === null) {
            return false;
        }

        $query = PagoMembresia::query();
        $query->getQuery()
            ->where('pasarela', $pago->pasarela)
            ->where('id_externo', $notificacion->idExterno)
            ->where('id', '!=', $pago->id);

        return $query->getQuery()->exists();
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
