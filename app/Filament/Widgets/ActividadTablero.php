<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\LeeFiltrosDelTablero;
use App\Tablero\ActividadDeComercios;
use App\Tablero\FormatoTablero;
use Filament\Widgets\Widget;

/**
 * "Comercios por actividad" (1z), o "Sedes por actividad" para el
 * administrador de comercio. Muestra las primeras filas y las inactivas;
 * "Ver los N" despliega el resto.
 */
class ActividadTablero extends Widget
{
    use LeeFiltrosDelTablero;

    protected static ?int $sort = 3;

    // Consultas livianas: se cargan con la página, sin esperar una petición por widget.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.actividad';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $periodo = $this->periodo();
        $alcance = $this->alcance();
        $filas = array_map(static fn (array $fila): array => [
            ...$fila,
            'variacion' => FormatoTablero::variacion($fila['transacciones'], $fila['transacciones_anterior']),
            'tendencia' => FormatoTablero::tendencia($fila['transacciones'], $fila['transacciones_anterior']),
            'valor' => FormatoTablero::dinero($fila['valor_beneficios']),
            'aviso' => FormatoTablero::sinRegistros($fila['dias_sin_registros']),
        ], app(ActividadDeComercios::class)($periodo, $alcance));
        $visibles = array_column(ActividadDeComercios::visibles($filas), 'id');

        return [
            'titulo' => $alcance->esGrupo() ? 'Comercios por actividad' : 'Sedes por actividad',
            'unidad' => $alcance->esGrupo() ? 'comercios' : 'sedes',
            'filas' => array_map(
                static fn (array $fila): array => [...$fila, 'visible' => in_array($fila['id'], $visibles, true)],
                $filas,
            ),
            'ocultas' => count($filas) - count($visibles),
            'vsMes' => $periodo->anterior()->mes(),
        ];
    }
}
