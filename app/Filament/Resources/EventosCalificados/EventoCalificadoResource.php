<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventosCalificados;

use App\Filament\Resources\EventosCalificados\Pages\CreateEventoCalificado;
use App\Filament\Resources\EventosCalificados\Pages\ListEventosCalificados;
use App\Filament\Resources\EventosCalificados\Schemas\EventoCalificadoForm;
use App\Filament\Resources\EventosCalificados\Tables\EventosCalificadosTable;
use App\Models\EventoCalificado;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Qualifying events: register them with their support file and close them.
 * There is no editing nor deleting; access is decided by
 * `EventoCalificadoPolicy` and every save goes through the level actions.
 */
class EventoCalificadoResource extends Resource
{
    protected static ?string $model = EventoCalificado::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $modelLabel = 'evento calificado';

    protected static ?string $pluralModelLabel = 'eventos calificados';

    protected static ?string $navigationLabel = 'Eventos calificados';

    protected static string|null|UnitEnum $navigationGroup = 'Niveles y reglas';

    protected static ?string $slug = 'eventos-calificados';

    /**
     * A merchant administrator only sees the events of their merchant.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['cliente', 'comercio']);
        $user = Auth::user();

        if ($user instanceof User && ! $user->esAdministradorDelGrupo()) {
            $query->getQuery()->where('comercio_id', $user->comercio_id ?? 0);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return EventoCalificadoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventosCalificadosTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListEventosCalificados::route('/'),
            'create' => CreateEventoCalificado::route('/create'),
        ];
    }
}
