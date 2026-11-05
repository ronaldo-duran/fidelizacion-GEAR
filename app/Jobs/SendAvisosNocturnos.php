<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Notificaciones\Notify;
use App\Enums\EventoNotificacion;
use App\Models\AvisoDescenso;
use App\Support\FormatoColombiano;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Envía, a la hora de `notificaciones.hora_avisos`, los avisos de descenso
 * que E3-05 registró de noche (E7-01, regla 10). Solo los del último día:
 * los de una noche perdida no se reenvían tarde (E3-05). Cada aviso queda
 * con `enviado_at`, y la restricción única de los envíos evita duplicados
 * si el proceso corre dos veces.
 */
class SendAvisosNocturnos implements ShouldQueue
{
    use Queueable;

    private const CHUNK = 200;

    public function __construct()
    {
        $cola = config('notificaciones.cola');
        $this->onQueue(is_string($cola) ? $cola : 'notificaciones');
    }

    public function handle(Notify $notify): void
    {
        $query = AvisoDescenso::query()->with('cliente')->where('created_at', '>=', CarbonImmutable::now()->subDay());
        $query->getQuery()->whereNull('enviado_at');

        $query->chunkById(self::CHUNK, static function (Collection $avisos) use ($notify): void {
            foreach ($avisos as $aviso) {
                $cliente = $aviso->cliente;

                if ($cliente === null) {
                    continue;
                }

                DB::transaction(static function () use ($notify, $aviso, $cliente): void {
                    $notify(EventoNotificacion::AvisoDescenso, $cliente, $aviso->id, [
                        'nivel' => $aviso->nivel_actual->getLabel(),
                        'nivel_resultante' => $aviso->nivel_resultante->getLabel(),
                        'puntos_que_vencen' => number_format($aviso->puntos_que_vencen, 0, ',', '.'),
                        'fecha_vencimiento' => FormatoColombiano::fecha($aviso->fecha_vencimiento),
                        'dias_antes' => (string) $aviso->dias_antes,
                        'enlace' => route('cliente.inicio'),
                    ]);

                    $aviso->forceFill(['enviado_at' => CarbonImmutable::now()])->save();
                });
            }
        });
    }
}
