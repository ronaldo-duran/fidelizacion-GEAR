<?php

declare(strict_types=1);

namespace App\Actions\Membresias;

use App\Enums\ResultadoNotificacionPago;
use App\Models\NotificacionPago;
use App\Pagos\FirmaInvalida;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Deja registrada una notificación con firma inválida (E6-01, regla 4.1).
 * Los datos del cuerpo no se usan para nada más: no son confiables. Para
 * que quien envíe basura no llene la tabla, un cuerpo grande no se guarda
 * entero y se registran como máximo unas cuantas por minuto y pasarela; las
 * demás solo reciben el 401.
 */
class RecordFirmaInvalida
{
    private const MAX_BYTES = 16384;

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
            'payload' => self::payload($error->payload),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private static function payload(array $payload): array
    {
        $bytes = strlen((string) json_encode($payload));

        return $bytes > self::MAX_BYTES ? ['truncado' => true, 'bytes' => $bytes] : $payload;
    }
}
