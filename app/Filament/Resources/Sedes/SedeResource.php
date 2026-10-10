<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sedes;

use App\Filament\Resources\Sedes\Pages\CreateSede;
use App\Filament\Resources\Sedes\Pages\EditSede;
use App\Filament\Resources\Sedes\Pages\ListSedes;
use App\Filament\Resources\Sedes\Schemas\SedeForm;
use App\Filament\Resources\Sedes\Tables\SedesTable;
use App\Models\Sede;
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

class SedeResource extends Resource
{
    protected static ?string $model = Sede::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $modelLabel = 'sede';

    protected static ?string $pluralModelLabel = 'sedes';

    protected static ?string $navigationLabel = 'Sedes';

    protected static string|null|UnitEnum $navigationGroup = 'Comercios';

    public static function canAccess(): bool
    {
        $usuario = Auth::user();

        return $usuario instanceof User
            && ($usuario->esAdministradorDelGrupo() || $usuario->esAdministradorDeComercio());
    }

    /**
     * Un administrador de comercio solo ve las sedes de su comercio.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $usuario = Auth::user();

        if ($usuario instanceof User && ! $usuario->esAdministradorDelGrupo()) {
            $query->where('comercio_id', $usuario->comercio_id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return SedeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SedesTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListSedes::route('/'),
            'create' => CreateSede::route('/create'),
            'edit' => EditSede::route('/{record}/edit'),
        ];
    }
}
