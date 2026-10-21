<?php

declare(strict_types=1);

namespace App\Jobs\Nocturno;

use App\Actions\Nocturno\RegisterAvisos;
use App\Models\MovimientoPuntos;
use App\Niveles\NivelEfectivo;

/**
 * 3. Demotion notices: customers with points that expire exactly on one of
 * the notice dates of the `niveles` rule.
 */
class RegisterAvisosDescenso extends NightlyJob
{
    public function handle(RegisterAvisos $register, NivelEfectivo $niveles): void
    {
        $corte = $this->corteDate();
        $fechas = array_map(
            static fn (int $dias): string => $corte->addDays($dias)->toDateString(),
            RegisterAvisos::diasAntes($niveles->definicionNiveles()),
        );

        if ($fechas === []) {
            return;
        }

        $ids = MovimientoPuntos::query()->toBase()
            ->where('puntos', '>', 0)
            ->whereIn('vigencia_hasta', $fechas)
            ->distinct()
            ->orderBy('cliente_id')
            ->pluck('cliente_id');

        $this->eachCliente(self::ids($ids), static fn (int $clienteId): array => $register($clienteId, $corte));
    }
}
