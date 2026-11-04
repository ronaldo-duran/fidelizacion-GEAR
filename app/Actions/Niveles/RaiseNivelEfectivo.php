<?php

declare(strict_types=1);

namespace App\Actions\Niveles;

use App\Actions\Notificaciones\Notify;
use App\Enums\EventoNotificacion;
use App\Enums\MotivoCambioNivel;
use App\Models\CambioNivel;
use App\Models\Cliente;
use App\Niveles\NivelEfectivo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Synchronous promotion (hard rule 3.6): recomputes the three sources and
 * leaves the effective level at the higher of the current one and the
 * computed one. It never lowers it, so any request may call it: the purchase
 * registration (E4-01) inside its own transaction, the qualifying events and
 * the memberships (E6-01).
 */
class RaiseNivelEfectivo
{
    private readonly NivelEfectivo $niveles;

    private readonly Notify $notifier;

    public function __construct(NivelEfectivo $niveles, Notify $notifier)
    {
        $this->niveles = $niveles;
        $this->notifier = $notifier;
    }

    public function __invoke(int $clienteId, MotivoCambioNivel $motivo): Cliente
    {
        if (! $motivo->isAscenso()) {
            throw new LogicException('A promotion needs a promotion reason.');
        }

        return DB::transaction(function () use ($clienteId, $motivo): Cliente {
            $cliente = $this->niveles->lockCliente($clienteId);
            $calculado = $this->niveles->refreshFuentes(
                $cliente,
                $this->niveles->definicionNiveles(),
                CarbonImmutable::today(),
            );

            if ($calculado->isAbove($cliente->nivel_efectivo)) {
                $cambio = $this->niveles->change($cliente, $calculado, $motivo);
                $this->notify($cliente, $cambio);
            }

            if (! $cliente->nivel_efectivo->isAbove($calculado)) {
                $cliente->forceFill(['proteccion_hasta' => null]);
            }

            $cliente->save();

            return $cliente;
        });
    }

    /**
     * Avisa la subida (E7-01). Si viene de pagar una membresía, el aviso de
     * membresía confirmada ya dice el nivel: no se manda un segundo correo.
     */
    private function notify(Cliente $cliente, CambioNivel $cambio): void
    {
        if ($cambio->motivo === MotivoCambioNivel::AscensoMembresia) {
            return;
        }

        ($this->notifier)(EventoNotificacion::SubidaNivel, $cliente, $cambio->id, [
            'nivel_anterior' => $cambio->nivel_anterior->getLabel(),
            'nivel' => $cambio->nivel_nuevo->getLabel(),
            'enlace' => route('cliente.inicio'),
        ]);
    }
}
