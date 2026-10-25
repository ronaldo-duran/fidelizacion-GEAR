<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\LeeFiltrosDelTablero;
use App\Tablero\FormatoTablero;
use App\Tablero\KpisDelPeriodo;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Los cuatro KPI del tablero (1z). Una subida del valor de los beneficios se
 * pinta como aviso: es costo para los comercios.
 */
class KpisTablero extends StatsOverviewWidget
{
    use LeeFiltrosDelTablero;

    protected static ?int $sort = 1;

    // Consultas livianas: se cargan con la página, sin esperar una petición por widget.
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 4;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $periodo = $this->periodo();
        $alcance = $this->alcance();
        $kpis = app(KpisDelPeriodo::class)($periodo, $alcance);
        $vsMes = ' vs. ' . $periodo->anterior()->mes();

        return [
            Stat::make(
                $alcance->esGrupo() ? 'Clientes inscritos' : 'Clientes que compraron',
                FormatoTablero::cifra($kpis['clientes']),
            )->description($alcance->esGrupo()
                ? '+' . FormatoTablero::cifra($kpis['clientes_nuevos']) . ' nuevos en el mes'
                : 'En ' . $periodo->mes()),
            Stat::make('Transacciones', FormatoTablero::cifra($kpis['transacciones']))
                ->description(
                    FormatoTablero::variacion($kpis['transacciones'], $kpis['transacciones_anterior']) . $vsMes,
                )
                ->color(match (FormatoTablero::tendencia($kpis['transacciones'], $kpis['transacciones_anterior'])) {
                    1 => 'success',
                    -1 => 'danger',
                    default => 'gray',
                }),
            Stat::make('Beneficios entregados', FormatoTablero::cifra($kpis['beneficios_entregados']))
                ->description(FormatoTablero::cifra($kpis['beneficios_por_100_compras']) . ' por cada 100 compras'),
            Stat::make('Valor de beneficios', FormatoTablero::dinero($kpis['valor_beneficios']))
                ->description(
                    FormatoTablero::variacion($kpis['valor_beneficios'], $kpis['valor_beneficios_anterior']) . $vsMes,
                )
                ->color(FormatoTablero::tendencia($kpis['valor_beneficios'], $kpis['valor_beneficios_anterior']) > 0
                    ? 'warning'
                    : 'gray'),
        ];
    }
}
