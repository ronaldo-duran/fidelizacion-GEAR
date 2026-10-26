<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Comercio;
use App\Models\User;
use App\Tablero\Periodo;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * Tablero del grupo (E10-01, pantalla 1z): selector de mes y los bloques de
 * KPI, clientes por nivel y actividad. El administrador de comercio ve el
 * mismo tablero filtrado a su comercio.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $navigationLabel = 'Tablero';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    public function getTitle(): string|Htmlable
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->esAdministradorDelGrupo()) {
            return 'Tablero del grupo';
        }

        $comercio = Comercio::query()->find($user->comercio_id);

        return 'Tablero de ' . ($comercio->razon_social ?? 'tu comercio');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('periodo')
                ->label('Periodo')
                ->options(Periodo::opciones(...))
                ->default(Periodo::actual()->clave())
                ->selectablePlaceholder(false),
        ]);
    }
}
