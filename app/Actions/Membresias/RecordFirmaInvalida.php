<?php

declare(strict_types=1);

namespace App\Actions\Membresias;

use App\Enums\ResultadoNotificacionPago;
use App\Models\NotificacionPago;
use App\Pagos\FirmaInvalida;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Deja registrada una notificación con firma inválida (E6-01, regla 4.1).
 * Su cuerpo no es confiable y puede traer datos personales, así que no se
 * guarda: solo la referencia que dice traer y el tamaño del cuerpo. Para que
 * quien envíe basura no llene la tabla, se registran como máximo unas
 * cuantas por minuto y pasarela; las demás solo reciben el 401. El proceso
 * nocturno borra las viejas (PurgeFirmasInvalidas).
 */
class RecordFirmaInvalida
{
    private const MAX_POR_MINUTO = 30;

    public function __invoke(string $pasarela, FirmaInvalida $error): ?NotificacionPago
    {
        $registrar = RateLimiter::attempt(
            "firma-invalida:{$pasarela}",
            self::MAX_POR_MINUTO,
            static fn (): bool => true,
        );

        if ($registrar !== true) {
            return null;
        }

        $referencia = data_get($error->payload, 'data.transaction.reference');

        return NotificacionPago::query()->create([
            'pasarela' => $pasarela,
            'referencia' => is_string($referencia) ? mb_substr($referencia, 0, 80) : null,
            'firma_valida' => false,
            'resultado' => ResultadoNotificacionPago::FirmaInvalida,
            'payload' => ['bytes' => strlen((string) json_encode($error->payload))],
        ]);
    }
}
