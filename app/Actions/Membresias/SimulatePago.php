<?php

declare(strict_types=1);

namespace App\Actions\Membresias;

use App\Enums\EstadoPago;
use App\Enums\ResultadoNotificacionPago;
use App\Models\PagoMembresia;
use App\Pagos\NotificacionPasarela;
use App\Pagos\SimuladaPasarela;
use LogicException;

/**
 * Aprueba o rechaza un pago de la pasarela simulada (solo local y E2E). Pasa
 * por el mismo camino que una notificación verificada de la pasarela real.
 */
class SimulatePago
{
    private readonly ApplyNotificacionPago $apply;

    public function __construct(ApplyNotificacionPago $apply)
    {
        $this->apply = $apply;
    }

    public function __invoke(PagoMembresia $pago, bool $aprobar): ResultadoNotificacionPago
    {
        if (! SimuladaPasarela::habilitada() || $pago->pasarela !== 'simulada') {
            throw new LogicException('Solo los pagos de la pasarela simulada se aprueban así.');
        }

        $estado = $aprobar ? EstadoPago::Aprobado : EstadoPago::Rechazado;

        return ($this->apply)('simulada', new NotificacionPasarela(
            $pago->referencia,
            'SIM-' . $pago->id,
            $estado,
            $aprobar ? 'APPROVED' : 'DECLINED',
            $pago->monto_cop,
            'COP',
            ['simulada' => true, 'referencia' => $pago->referencia, 'aprobar' => $aprobar],
        ));
    }
}
