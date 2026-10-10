<?php

declare(strict_types=1);

namespace App\Filament\Resources\Comercios;

use App\Filament\Resources\Comercios\Pages\CreateComercio;
use App\Filament\Resources\Comercios\Pages\EditComercio;
use App\Filament\Resources\Comercios\Pages\ListComercios;
use App\Filament\Resources\Comercios\Schemas\ComercioForm;
use App\Filament\Resources\Comercios\Tables\ComerciosTable;
use App\Models\Comercio;
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

class ComercioResource extends Resource
{
    protected static ?string $model = Comercio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $modelLabel = 'comercio';

    protected static ?string $pluralModelLabel = 'comercios';

    protected static ?string $navigationLabel = 'Comercios';

    protected static string|null|UnitEnum $navigationGroup = 'Comercios';

    /**
     * El grupo y los administradores de comercio entran; el cajero no.
     */
    public static function canAccess(): bool
    {
        $usuario = Auth::user();

        return $usuario instanceof User
            && ($usuario->esAdministradorDelGrupo() || $usuario->esAdministradorDeComercio());
    }

    /**
     * Un administrador de comercio solo ve el suyo; el grupo ve todos.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $usuario = Auth::user();

        if ($usuario instanceof User && ! $usuario->esAdministradorDelGrupo()) {
            $query->whereKey($usuario->comercio_id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return ComercioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComerciosTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListComercios::route('/'),
            'create' => CreateComercio::route('/create'),
            'edit' => EditComercio::route('/{record}/edit'),
        ];
    }
}
