<?php

declare(strict_types=1);

namespace App\Tablero;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Redenciones del periodo y su valor otorgado (regla dura 3.11), en total y
 * por comercio o sede (E5-02). Con el alcance de un comercio, solo cuentan
 * las suyas.
 */
class BeneficiosDelPeriodo
{
    /**
     * @return array{entregados: int, valor: int}
     */
    public function total(Periodo $periodo, AlcanceTablero $alcance): array
    {
        $fila = $this->redenciones($periodo, $alcance)
            ->selectRaw('count(*) as entregados, coalesce(sum(valor_otorgado_cop), 0) as valor')
            ->first();

        $entregados = $fila === null ? null : $fila->entregados;
        $valor = $fila === null ? null : $fila->valor;

        return [
            'entregados' => is_numeric($entregados) ? (int) $entregados : 0,
            'valor' => is_numeric($valor) ? (int) $valor : 0,
        ];
    }

    /**
     * Por comercio (grupo) o por sede (comercio), con la misma clave que la
     * tabla de actividad.
     *
     * @return array<int, array{entregados: int, valor: int}>
     */
    public function porFila(Periodo $periodo, AlcanceTablero $alcance): array
    {
        $clave = $alcance->esGrupo() ? 'comercio_id' : 'sede_id';
        $filas = $this->redenciones($periodo, $alcance)
            ->whereNotNull($clave)
            ->groupBy($clave)
            ->selectRaw("{$clave} as clave, count(*) as entregados, coalesce(sum(valor_otorgado_cop), 0) as valor")
            ->get();
        $porFila = [];

        foreach ($filas as $fila) {
            if (is_numeric($fila->clave) && is_numeric($fila->entregados) && is_numeric($fila->valor)) {
                $porFila[(int) $fila->clave] = ['entregados' => (int) $fila->entregados, 'valor' => (int) $fila->valor];
            }
        }

        return $porFila;
    }

    private function redenciones(Periodo $periodo, AlcanceTablero $alcance): Builder
    {
        return DB::table('redenciones')
            ->whereBetween('created_at', [$periodo->inicio, $periodo->fin])
            ->when(
                ! $alcance->esGrupo(),
                static fn (Builder $query) => $query->where('comercio_id', $alcance->comercioId),
            );
    }
}
