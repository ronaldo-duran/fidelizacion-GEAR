<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\EstadoEnvio;
use App\Mail\CorreoNotificacion;
use App\Models\EnvioNotificacion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Manda el correo de un envío pendiente (E7-01), en la cola de
 * notificaciones. Si el proveedor falla, reintenta con espera; al agotar los
 * intentos el envío queda fallido con su error. Nunca relanza la excepción:
 * con la cola síncrona, el error no debe romper la compra o el pago que
 * originaron el aviso.
 */
class SendEnvioCorreo implements ShouldQueue
{
    use Queueable;

    private const ESPERA_SEGUNDOS = [60, 300];

    /** No es readonly: la cola lo restaura al deserializar. */
    public int $envioId;

    /** Manda sobre el `tries` del supervisor de Horizon, que es 1. */
    public int $tries;

    public function __construct(int $envioId)
    {
        $this->envioId = $envioId;
        $this->tries = self::maxIntentos();
        $cola = config('notificaciones.cola');
        $this->onQueue(is_string($cola) ? $cola : 'notificaciones');
    }

    public static function maxIntentos(): int
    {
        $intentos = config('notificaciones.intentos');

        return is_int($intentos) && $intentos > 0 ? $intentos : 1;
    }

    public function handle(): void
    {
        $envio = EnvioNotificacion::query()->find($this->envioId);

        if ($envio === null || $envio->estado !== EstadoEnvio::Pendiente || $envio->destinatario === null) {
            return;
        }

        $intento = $this->attempts();

        try {
            Mail::to($envio->destinatario)->send(new CorreoNotificacion($envio));
        } catch (Throwable $exception) {
            $this->fallo($envio, $intento, $exception);

            return;
        }

        $envio->forceFill([
            'estado' => EstadoEnvio::Enviado,
            'enviado_at' => CarbonImmutable::now(),
            'intentos' => $intento,
            'error' => null,
        ])->save();
    }

    private function fallo(EnvioNotificacion $envio, int $intento, Throwable $exception): void
    {
        $agotado = $intento >= self::maxIntentos();

        $envio->forceFill([
            'estado' => $agotado ? EstadoEnvio::Fallido : EstadoEnvio::Pendiente,
            'intentos' => $intento,
            'error' => mb_substr($exception->getMessage(), 0, 1000),
        ])->save();

        if (! $agotado) {
            $this->release(self::ESPERA_SEGUNDOS[min($intento, count(self::ESPERA_SEGUNDOS)) - 1]);
        }
    }
}
