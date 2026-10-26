<?php

declare(strict_types=1);

namespace App\Tablero;

/**
 * Redenciones del periodo y su valor otorgado (regla dura 3.11), en total y
 * por comercio o sede. Las redenciones llegan con E5-02: mientras tanto todo
 * es cero, y nunca se consulta una tabla que no existe.
 */
class BeneficiosDelPeriodo
{
    /**
     * @return array{entregados: int, valor: int}
     */
    public function total(Periodo $periodo, AlcanceTablero $alcance): array
    {
        // TODO(E5-02): contar las redenciones del periodo y sumar valor_otorgado_cop, con el alcance.
        unset($periodo, $alcance);

        return ['entregados' => 0, 'valor' => 0];
    }

    /**
     * Por comercio (grupo) o por sede (comercio), con la misma clave que la
     * tabla de actividad.
     *
     * @return array<int, array{entregados: int, valor: int}>
     */
    public function porFila(Periodo $periodo, AlcanceTablero $alcance): array
    {
        // TODO(E5-02): agrupar las redenciones del periodo por comercio_id, o por sede con el alcance de comercio.
        unset($periodo, $alcance);

        return [];
    }
}
