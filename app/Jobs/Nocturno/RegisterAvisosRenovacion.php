<?php

declare(strict_types=1);

namespace App\Jobs\Nocturno;

use App\Actions\Membresias\RegisterAvisosRenovacion as RegisterAvisos;
use App\Models\Cliente;

/**
 * Avisos de renovación (E6-01), después de los de descenso: clientes cuya
 * membresía termina exactamente en uno de los días de aviso.
 */
class RegisterAvisosRenovacion extends NightlyJob
{
    public function handle(RegisterAvisos $register): void
    {
        $corte = $this->corteDate();
        $fechas = array_map(
            static fn (int $dias): string => $corte->addDays($dias)->toDateString(),
            RegisterAvisos::diasAntes(),
        );

        if ($fechas === []) {
            return;
        }

        $ids = Cliente::query()->toBase()
            ->whereIn('membresia_vigente_hasta', $fechas)
            ->orderBy('id')
            ->pluck('id');

        $this->eachCliente(self::ids($ids), static fn (int $clienteId): array => $register($clienteId, $corte));
    }
}
