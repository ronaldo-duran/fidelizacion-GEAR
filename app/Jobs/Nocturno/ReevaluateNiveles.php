<?php

declare(strict_types=1);

namespace App\Jobs\Nocturno;

use App\Actions\Niveles\ReevaluateNivelEfectivo;
use App\Enums\Nivel;
use App\Models\Cliente;

/**
 * 2. Level re-evaluation, after the expiry. It goes over every customer above
 * Bronce: that covers expired points, expired memberships and events, closed
 * enrollments and protections that end. A Bronce customer cannot drop.
 */
class ReevaluateNiveles extends NightlyJob
{
    public function handle(ReevaluateNivelEfectivo $reevaluate): void
    {
        $ids = Cliente::query()->toBase()
            ->where('nivel_efectivo', '!=', Nivel::Bronce->value)
            ->orderBy('id')
            ->pluck('id');

        $this->eachCliente(self::ids($ids), static fn (int $clienteId): Cliente => $reevaluate($clienteId));
    }
}
