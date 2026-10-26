<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Nivel;
use App\Filament\Widgets\Concerns\LeeFiltrosDelTablero;
use App\Tablero\ClientesPorNivel;
use App\Tablero\FormatoTablero;
use Filament\Widgets\Widget;

/**
 * Clientes por nivel (1z): barra apilada con los colores de nivel, leyenda con
 * conteo y porcentaje, y el origen de los Diamante.
 */
class ClientesPorNivelTablero extends Widget
{
    use LeeFiltrosDelTablero;

    protected static ?int $sort = 2;

    // Consultas livianas: se cargan con la página, sin esperar una petición por widget.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.clientes-por-nivel';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $datos = app(ClientesPorNivel::class)($this->periodo(), $this->alcance());
        $origen = $datos['diamante_origen'];
        $diamantes = $datos['niveles'][Nivel::Diamante->value]['clientes'];

        return [
            'resumen' => 'De ' . FormatoTablero::cifra($diamantes) . ' Diamante: '
                . FormatoTablero::cifra($origen['puntos']) . ' por puntos, '
                . FormatoTablero::cifra($origen['membresia']) . ' por suscripción, '
                . FormatoTablero::cifra($origen['evento']) . ' por inmueble',
            'niveles' => array_map(
                static fn (Nivel $nivel): array => [
                    'nivel' => $nivel->value,
                    'nombre' => $nivel->getLabel(),
                    ...$datos['niveles'][$nivel->value],
                ],
                Nivel::cases(),
            ),
        ];
    }
}
