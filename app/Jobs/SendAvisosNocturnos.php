<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Notificaciones\Notify;
use App\Enums\EventoNotificacion;
use App\Models\AvisoDescenso;
use App\Models\AvisoRenovacion;
use App\Support\FormatoColombiano;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Envía, a la hora de `notificaciones.hora_avisos`, los avisos de descenso
 * que E3-05 registró de noche (E7-01, regla 10) y los de renovación de la
 * membresía (E6-01). Solo los del último día:
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
        $this->descenso($notify);
        $this->renovacion($notify);
    }

    private function descenso(Notify $notify): void
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

    private function renovacion(Notify $notify): void
    {
        $query = AvisoRenovacion::query()->with('cliente')->where('created_at', '>=', CarbonImmutable::now()->subDay());
        $query->getQuery()->whereNull('enviado_at');

        $query->chunkById(self::CHUNK, static function (Collection $avisos) use ($notify): void {
            foreach ($avisos as $aviso) {
                $nivel = $aviso->cliente?->nivel_por_membresia;

                if ($aviso->cliente === null || $nivel === null) {
                    continue;
                }

                $cliente = $aviso->cliente;

                DB::transaction(static function () use ($notify, $aviso, $cliente, $nivel): void {
                    $notify(EventoNotificacion::MembresiaPorVencer, $cliente, $aviso->id, [
                        'nivel' => $nivel->getLabel(),
                        'vigente_hasta' => FormatoColombiano::fechaConAnio($aviso->vigente_hasta),
                        'dias_antes' => (string) $aviso->dias_antes,
                        'enlace' => route('cliente.membresia'),
                    ]);

                    $aviso->forceFill(['enviado_at' => CarbonImmutable::now()])->save();
                });
            }
        });
    }
}
