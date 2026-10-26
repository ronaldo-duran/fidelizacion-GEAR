<?php

declare(strict_types=1);

namespace App\Tablero;

use App\Enums\EstadoTransaccion;
use App\Enums\Nivel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * La foto de hoy de los clientes por nivel efectivo, con su porcentaje, y el
 * origen de los Diamante (E10-01, regla 7). Un Diamante cuenta en una sola
 * fuente, en el orden puntos → suscripción → inmueble; las tres fuentes siguen
 * separadas en el cliente (regla dura 3.5), esto solo decide la columna.
 *
 * Con el alcance de un comercio, cuenta los clientes que le compraron en el
 * periodo.
 */
class ClientesPorNivel
{
    private const DIAMANTE = 'diamante';

    /**
     * @return array{
     *     niveles: array<string, array{clientes: int, porcentaje: int}>,
     *     diamante_origen: array{puntos: int, membresia: int, evento: int}
     * }
     */
    public function __invoke(Periodo $periodo, AlcanceTablero $alcance): array
    {
        $conteos = $this->clientes($periodo, $alcance)
            ->select('nivel_efectivo', DB::raw('count(*) as clientes'))
            ->groupBy('nivel_efectivo')
            ->pluck('clientes', 'nivel_efectivo')
            ->map(ValorDeBase::entero(...))
            ->all();
        $total = array_sum($conteos);
        $niveles = [];

        foreach (Nivel::cases() as $nivel) {
            $clientes = $conteos[$nivel->value] ?? 0;
            $niveles[$nivel->value] = [
                'clientes' => $clientes,
                'porcentaje' => $total === 0 ? 0 : (int) round($clientes * 100 / $total),
            ];
        }

        return ['niveles' => $niveles, 'diamante_origen' => $this->origenDiamante($periodo, $alcance)];
    }

    /**
     * @return array{puntos: int, membresia: int, evento: int}
     */
    private function origenDiamante(Periodo $periodo, AlcanceTablero $alcance): array
    {
        $diamantes = $this->clientes($periodo, $alcance)->where('nivel_efectivo', self::DIAMANTE);
        $puntos = (clone $diamantes)->where('nivel_por_puntos', self::DIAMANTE)->count();
        $membresia = (clone $diamantes)
            ->where('nivel_por_puntos', '<>', self::DIAMANTE)
            ->where('nivel_por_membresia', self::DIAMANTE)
            ->count();

        return [
            'puntos' => $puntos,
            'membresia' => $membresia,
            'evento' => $diamantes->count() - $puntos - $membresia,
        ];
    }

    private function clientes(Periodo $periodo, AlcanceTablero $alcance): Builder
    {
        return DB::table('clientes')->when(
            ! $alcance->esGrupo(),
            static fn (Builder $query) => $query->whereIn('id', DB::table('transacciones')
                ->select('cliente_id')
                ->where('comercio_id', $alcance->comercioId)
                ->where('estado', EstadoTransaccion::Acreditada->value)
                ->whereBetween('created_at', [$periodo->inicio, $periodo->fin])),
        );
    }
}
