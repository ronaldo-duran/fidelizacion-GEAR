<?php

declare(strict_types=1);

namespace App\Actions\Notificaciones;

use App\Enums\CanalNotificacion;
use App\Models\EnvioNotificacion;
use Carbon\CarbonImmutable;

/**
 * Marca como leídos los avisos en la app de un cliente (E7-01), cuando abre
 * su bandeja. Solo los suyos y solo los que no había leído.
 */
class MarkAvisosLeidos
{
    public function __invoke(int $clienteId): int
    {
        $query = EnvioNotificacion::query();
        $query->getQuery()
            ->where('cliente_id', $clienteId)
            ->where('canal', CanalNotificacion::App->value)
            ->whereNull('leido_at');

        return $query->update(['leido_at' => CarbonImmutable::now()]);
    }
}
