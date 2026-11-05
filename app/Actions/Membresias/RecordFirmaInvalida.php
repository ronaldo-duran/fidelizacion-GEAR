<?php

declare(strict_types=1);

namespace App\Actions\Membresias;

use App\Enums\ResultadoNotificacionPago;
use App\Models\NotificacionPago;
use App\Pagos\FirmaInvalida;

/**
 * Deja registrada una notificación con firma inválida (E6-01, regla 4.1).
 * Los datos del cuerpo no se usan para nada más: no son confiables.
 */
class RecordFirmaInvalida
{
    public function __invoke(string $pasarela, FirmaInvalida $error): NotificacionPago
    {
        $referencia = data_get($error->payload, 'data.transaction.reference');

        return NotificacionPago::query()->create([
            'pasarela' => $pasarela,
            'referencia' => is_string($referencia) ? mb_substr($referencia, 0, 80) : null,
            'firma_valida' => false,
            'resultado' => ResultadoNotificacionPago::FirmaInvalida,
            'payload' => $error->payload,
        ]);
    }
}
