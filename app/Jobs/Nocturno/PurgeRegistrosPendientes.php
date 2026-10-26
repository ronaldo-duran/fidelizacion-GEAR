<?php

declare(strict_types=1);

namespace App\Jobs\Nocturno;

use App\Models\RegistroPendiente;

/**
 * 6. Borra los registros pendientes que vencieron antes del corte sin
 * verificar el celular (E8-02). Guardan datos personales de alguien que no
 * llegó a ser cliente, así que no se conservan (minimización de datos, Ley
 * 1581).
 */
class PurgeRegistrosPendientes extends NightlyJob
{
    public function handle(): void
    {
        RegistroPendiente::query()->toBase()
            ->where('vence_at', '<', $this->corteDate()->toDateTimeString())
            ->delete();
    }
}
