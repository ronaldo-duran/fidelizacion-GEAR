<?php

declare(strict_types=1);

namespace App\Tablero;

use App\Enums\EstadoTransaccion;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Los cuatro KPI del periodo (E10-01, regla 5): clientes, transacciones,
 * beneficios entregados y su valor, cada uno con su mes anterior. Con el
 * alcance de un comercio, "clientes inscritos" pasa a ser "clientes que
 * compraron" en el mes.
 */
class KpisDelPeriodo
{
    private readonly BeneficiosDelPeriodo $beneficios;

    public function __construct(BeneficiosDelPeriodo $beneficios)
    {
        $this->beneficios = $beneficios;
    }

    /**
     * @return array{
     *     clientes: int,
     *     clientes_nuevos: int,
     *     transacciones: int,
     *     transacciones_anterior: int,
     *     beneficios_entregados: int,
     *     beneficios_por_100_compras: int,
     *     valor_beneficios: int,
     *     valor_beneficios_anterior: int
     * }
     */
    public function __invoke(Periodo $periodo, AlcanceTablero $alcance): array
    {
        $anterior = $periodo->anterior();
        $transacciones = $this->transacciones($periodo, $alcance);
        $beneficios = $this->beneficios->total($periodo, $alcance);

        return [
            'clientes' => $alcance->esGrupo()
                ? $this->clientesInscritos($periodo)
                : $this->clientesQueCompraron($periodo, $alcance),
            'clientes_nuevos' => $alcance->esGrupo() ? $this->clientesNuevos($periodo) : 0,
            'transacciones' => $transacciones,
            'transacciones_anterior' => $this->transacciones($anterior, $alcance),
            'beneficios_entregados' => $beneficios['entregados'],
            'beneficios_por_100_compras' => $transacciones === 0
                ? 0
                : (int) round($beneficios['entregados'] * 100 / $transacciones),
            'valor_beneficios' => $beneficios['valor'],
            'valor_beneficios_anterior' => $this->beneficios->total($anterior, $alcance)['valor'],
        ];
    }

    private function clientesInscritos(Periodo $periodo): int
    {
        return DB::table('clientes')->where('created_at', '<=', $periodo->fin)->count();
    }

    private function clientesNuevos(Periodo $periodo): int
    {
        return DB::table('clientes')->whereBetween('created_at', [$periodo->inicio, $periodo->fin])->count();
    }

    private function clientesQueCompraron(Periodo $periodo, AlcanceTablero $alcance): int
    {
        return $this->compras($periodo, $alcance)
            ->where('estado', EstadoTransaccion::Acreditada->value)
            ->whereNotNull('cliente_id')
            ->distinct()
            ->count('cliente_id');
    }

    private function transacciones(Periodo $periodo, AlcanceTablero $alcance): int
    {
        return $this->compras($periodo, $alcance)->count();
    }

    /**
     * Las compras del periodo que no están anuladas (las pendientes cuentan:
     * miden la actividad del comercio), con el alcance.
     */
    private function compras(Periodo $periodo, AlcanceTablero $alcance): Builder
    {
        return DB::table('transacciones')
            ->whereBetween('created_at', [$periodo->inicio, $periodo->fin])
            ->where('estado', '<>', EstadoTransaccion::Anulada->value)
            ->when(
                ! $alcance->esGrupo(),
                static fn (Builder $query) => $query->where('comercio_id', $alcance->comercioId),
            );
    }
}
