<?php

declare(strict_types=1);

namespace App\Jobs\Nocturno;

use App\Models\CredencialQr;

/**
 * 5. Borra las credenciales QR vencidas antes del corte. Viven segundos y no
 * guardan historia. Se conservan las de beneficio ya gastadas: su redención
 * guarda con qué código se hizo (E5-02). Sus reservas de código corto se
 * borran en cascada.
 */
class PurgeCredenciales extends NightlyJob
{
    public function handle(): void
    {
        CredencialQr::query()->toBase()
            ->where('vence_at', '<', $this->corteDate()->toDateTimeString())
            ->whereNull('usado_at')
            ->delete();
    }
}
