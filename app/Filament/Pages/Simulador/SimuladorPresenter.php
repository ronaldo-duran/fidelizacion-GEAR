<?php

declare(strict_types=1);

namespace App\Filament\Pages\Simulador;

use App\Reglas\PuntosDesglose;
use App\Reglas\SimulacionLinea;
use App\Reglas\SimulacionResultado;

/**
 * Turns a simulation into plain arrays the Livewire page can keep between
 * requests and the view can print.
 */
class SimuladorPresenter
{
    /**
     * @return array{lineas: list<array<string, mixed>>, total_vigente: int, total_borrador: int}
     */
    public static function present(SimulacionResultado $resultado): array
    {
        return [
            'lineas' => array_map(static fn (SimulacionLinea $linea): array => [
                'monto' => $linea->compra->monto,
                'fecha' => $linea->compra->fecha->format('Y-m-d H:i'),
                'vigente' => self::desglose($linea->vigente),
                'borrador' => self::desglose($linea->borrador),
                'diferencia' => $linea->diferencia(),
            ], $resultado->lineas),
            'total_vigente' => $resultado->totalVigente(),
            'total_borrador' => $resultado->totalBorrador(),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private static function desglose(PuntosDesglose $desglose): array
    {
        $acumulacion = $desglose->acumulacion;
        $bonificacion = $desglose->bonificacion;

        return [
            'base' => $desglose->base,
            'regla' => $acumulacion === null ? null : "{$acumulacion->codigo} {$acumulacion->versionLabel()}",
            'bonificacion' => $bonificacion === null
                ? null
                : "{$bonificacion->codigo} {$bonificacion->versionLabel()} (×{$desglose->multiplicador})",
            'tope' => $desglose->tope,
            'puntos' => $desglose->puntos,
        ];
    }
}
