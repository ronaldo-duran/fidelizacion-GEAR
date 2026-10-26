<?php

declare(strict_types=1);

namespace App\Tablero;

use App\Enums\EstadoTransaccion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * "Comercios por actividad" (E10-01, regla 8): por comercio activo, compras
 * del mes con su variación, beneficios y su valor, ordenados por compras. Un
 * comercio sin compras en los últimos N días se marca inactivo. Con el
 * alcance de un comercio, las filas son sus sedes activas.
 *
 * Tres consultas agrupadas en total, sin una por fila.
 */
class ActividadDeComercios
{
    private readonly BeneficiosDelPeriodo $beneficios;

    public function __construct(BeneficiosDelPeriodo $beneficios)
    {
        $this->beneficios = $beneficios;
    }

    /**
     * @return list<array{
     *     id: int,
     *     nombre: string,
     *     detalle: string,
     *     transacciones: int,
     *     transacciones_anterior: int,
     *     beneficios: int,
     *     valor_beneficios: int,
     *     dias_sin_registros: int|null,
     *     inactivo: bool
     * }>
     */
    public function __invoke(Periodo $periodo, AlcanceTablero $alcance): array
    {
        $clave = $alcance->esGrupo() ? 'comercio_id' : 'sede_id';
        $actual = $this->conteo($clave, $periodo, $alcance);
        $anterior = $this->conteo($clave, $periodo->anterior(), $alcance);
        $ultimas = $this->compras($alcance)
            ->select($clave, DB::raw('max(created_at) as ultima'))
            ->groupBy($clave)
            ->pluck('ultima', $clave);
        $beneficios = $this->beneficios->porFila($periodo, $alcance);
        $hoy = CarbonImmutable::today();
        $filas = [];

        foreach ($this->filas($alcance) as $fila) {
            $id = ValorDeBase::entero($fila->id ?? null);
            $ultima = $ultimas[$id] ?? null;
            $dias = is_string($ultima) ? (int) CarbonImmutable::parse($ultima)->startOfDay()->diffInDays($hoy) : null;
            $filas[] = [
                'id' => $id,
                'nombre' => ValorDeBase::texto($fila->nombre ?? null),
                'detalle' => ValorDeBase::texto($fila->detalle ?? null),
                'transacciones' => ValorDeBase::entero($actual[$id] ?? 0),
                'transacciones_anterior' => ValorDeBase::entero($anterior[$id] ?? 0),
                'beneficios' => $beneficios[$id]['entregados'] ?? 0,
                'valor_beneficios' => $beneficios[$id]['valor'] ?? 0,
                'dias_sin_registros' => $dias,
                'inactivo' => $dias === null || $dias >= self::diasInactivo(),
            ];
        }

        // Más compras primero; a igual número, por nombre.
        usort($filas, static fn (array $una, array $otra): int => [$otra['transacciones'], $una['nombre']]
            <=> [$una['transacciones'], $otra['nombre']]);

        return $filas;
    }

    /**
     * Las filas que se ven antes de "Ver los N": las primeras del orden y
     * todas las inactivas.
     *
     * @template T of array<string, mixed>
     *
     * @param  list<T>  $filas
     * @return list<T>
     */
    public static function visibles(array $filas): array
    {
        $primeras = config('tablero.comercios_before_more');
        $limite = is_int($primeras) ? $primeras : 0;

        return array_values(array_filter(
            $filas,
            static fn (array $fila, int $posicion): bool => $posicion < $limite
                || ($fila['inactivo'] ?? false) === true,
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * @return array<int|string, mixed>
     */
    private function conteo(string $clave, Periodo $periodo, AlcanceTablero $alcance): array
    {
        return $this->compras($alcance)
            ->whereBetween('created_at', [$periodo->inicio, $periodo->fin])
            ->groupBy($clave)
            ->select($clave, DB::raw('count(*) as total'))
            ->pluck('total', $clave)
            ->all();
    }

    private function compras(AlcanceTablero $alcance): Builder
    {
        return DB::table('transacciones')
            ->where('estado', '<>', EstadoTransaccion::Anulada->value)
            ->when(
                ! $alcance->esGrupo(),
                static fn (Builder $query) => $query->where('comercio_id', $alcance->comercioId),
            );
    }

    /**
     * Los comercios activos con su categoría, o las sedes activas del comercio.
     *
     * @return Collection<int, stdClass>
     */
    private function filas(AlcanceTablero $alcance): Collection
    {
        if ($alcance->esGrupo()) {
            return DB::table('comercios')
                ->join('categorias', 'categorias.id', '=', 'comercios.categoria_id')
                ->where('comercios.activo', true)
                ->select('comercios.id', 'comercios.razon_social as nombre', 'categorias.nombre as detalle')
                ->get();
        }

        return DB::table('sedes')
            ->where('comercio_id', $alcance->comercioId)
            ->where('activa', true)
            ->select('id', 'nombre', 'direccion as detalle')
            ->get();
    }

    private static function diasInactivo(): int
    {
        $dias = config('tablero.inactivity_days');

        return is_int($dias) ? $dias : 0;
    }
}
