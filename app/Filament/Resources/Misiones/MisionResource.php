<?php

declare(strict_types=1);

namespace App\Filament\Resources\Misiones;

use App\Enums\TipoRegla;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Misiones\Pages\CreateMision;
use App\Filament\Resources\Misiones\Pages\ListMisiones;
use App\Filament\Resources\Misiones\Pages\NewMisionVersion;
use App\Filament\Resources\Misiones\Pages\ViewMision;
use App\Filament\Resources\Misiones\RelationManagers\MisionVersionsRelationManager;
use App\Filament\Resources\Misiones\Schemas\MisionForm;
use App\Filament\Resources\Misiones\Tables\MisionesTable;
use App\Filament\Resources\Reglas\Schemas\ReglaInfolist;
use App\Models\Regla;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Misiones de los comercios (E3-06). Son reglas de tipo `mision`: guardar
 * crea una versión nueva por las Actions del motor, que validan los límites
 * del comercio. El grupo ve todas; el administrador de comercio, las suyas.
 */
class MisionResource extends BaseResource
{
    protected static ?string $model = Regla::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $modelLabel = 'misión';

    protected static ?string $pluralModelLabel = 'misiones';

    protected static ?string $navigationLabel = 'Misiones';

    protected static string|null|UnitEnum $navigationGroup = 'Niveles y reglas';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'misiones';

    protected static ?string $recordTitleAttribute = 'codigo';

    /**
     * El grupo ve todas; el administrador de comercio, las de su comercio
     * (la consulta las filtra). El cajero no entra.
     */
    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && $user->activo
            && ($user->esAdministradorDelGrupo() || $user->esAdministradorDeComercio());
    }

    /**
     * El comercio de la misión lo valida `CreateRegla` al guardar.
     */
    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('update', $record);
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('update', $record);
    }

    /**
     * Cada misión aparece una vez, por su última versión.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $query->getQuery()->whereNull('vigente_hasta')->where('tipo', TipoRegla::Mision->value);
        $user = Auth::user();

        if ($user instanceof User && ! $user->esAdministradorDelGrupo()) {
            // Texto: PostgreSQL compara el valor JSON extraído como texto (regla 3.13).
            $query->getQuery()->where('definicion->comercio_id', (string) ($user->comercio_id ?? 0));
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return MisionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReglaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MisionesTable::configure($table);
    }

    /**
     * @return array<int, class-string<RelationManager>>
     */
    public static function getRelations(): array
    {
        return [
            MisionVersionsRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListMisiones::route('/'),
            'create' => CreateMision::route('/create'),
            'view' => ViewMision::route('/{record}'),
            'edit' => NewMisionVersion::route('/{record}/nueva-version'),
        ];
    }
}
