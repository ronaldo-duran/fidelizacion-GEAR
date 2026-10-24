<?php

declare(strict_types=1);

namespace App\Jobs\Nocturno;

use App\Models\CodigoVerificacion;

/**
 * 5. Borra los códigos de verificación vencidos antes del corte. Viven
 * minutos y guardan un celular y una IP, así que no se conservan más de lo
 * necesario (minimización de datos, Ley 1581). Los límites de envío viven en
 * la caché, no aquí.
 */
class PurgeCodigosVerificacion extends NightlyJob
{
    public function handle(): void
    {
        CodigoVerificacion::query()->toBase()
            ->where('vence_at', '<', $this->corteDate()->toDateTimeString())
            ->delete();
    }
}
