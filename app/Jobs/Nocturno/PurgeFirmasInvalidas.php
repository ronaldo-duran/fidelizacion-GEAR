<?php

declare(strict_types=1);

namespace App\Jobs\Nocturno;

use App\Models\NotificacionPago;

/**
 * 7. Borra las notificaciones de pago con firma inválida más viejas que la
 * retención (E6-01). Llegan de cualquiera sin autenticar y no sostienen
 * ningún pago, así que no se conservan para siempre. Las de firma válida
 * no se tocan: son el registro de cada pago.
 */
class PurgeFirmasInvalidas extends NightlyJob
{
    public function handle(): void
    {
        $dias = config('pagos.retencion_firmas_invalidas_dias');

        NotificacionPago::query()->toBase()
            ->where('firma_valida', false)
            ->where('created_at', '<', $this->corteDate()->subDays(is_int($dias) ? $dias : 90)->toDateTimeString())
            ->delete();
    }
}
