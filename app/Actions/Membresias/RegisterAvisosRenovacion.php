<?php

declare(strict_types=1);

namespace App\Actions\Membresias;

use App\Models\AvisoRenovacion;
use App\Niveles\NivelEfectivo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Registra los avisos de renovación que se le deben a un cliente (E6-01,
 * regla 10): uno por cada número de días de `pagos.avisos_renovacion_dias`
 * en que su membresía termina exactamente. `membresia_vigente_hasta` ya es
 * el fin del periodo más lejano, así que quien pagó otro periodo después no
 * recibe aviso.
 *
 * Con la fila del cliente bloqueada y la restricción única detrás, correr
 * dos veces no duplica avisos. Los de una noche perdida no se registran tarde.
 */
class RegisterAvisosRenovacion
{
    private readonly NivelEfectivo $niveles;

    public function __construct(NivelEfectivo $niveles)
    {
        $this->niveles = $niveles;
    }

    /**
     * @return list<AvisoRenovacion> los avisos registrados
     */
    public function __invoke(int $clienteId, CarbonImmutable $corte): array
    {
        return DB::transaction(function () use ($clienteId, $corte): array {
            $cliente = $this->niveles->lockCliente($clienteId);
            $hasta = $cliente->membresia_vigente_hasta;
            $avisos = [];

            foreach (self::diasAntes() as $dias) {
                if ($hasta === null || ! $hasta->isSameDay($corte->startOfDay()->addDays($dias))) {
                    continue;
                }

                $aviso = AvisoRenovacion::query()->firstOrCreate([
                    'cliente_id' => $cliente->id,
                    'vigente_hasta' => $hasta->toDateString(),
                    'dias_antes' => $dias,
                ]);

                if ($aviso->wasRecentlyCreated) {
                    $avisos[] = $aviso;
                }
            }

            return $avisos;
        });
    }

    /**
     * Días de aviso antes del fin de la membresía (supuesto del fixture).
     *
     * @return list<int>
     */
    public static function diasAntes(): array
    {
        $dias = config('pagos.avisos_renovacion_dias');

        return array_values(array_filter(
            is_array($dias) ? $dias : [],
            static fn (mixed $dia): bool => is_int($dia) && $dia > 0,
        ));
    }
}
