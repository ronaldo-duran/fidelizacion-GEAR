<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clientes;

use App\Filament\Resources\Clientes\Pages\ListClientes;
use App\Filament\Resources\Clientes\Pages\ViewCliente;
use App\Filament\Resources\Clientes\RelationManagers\CambiosNivelRelationManager;
use App\Filament\Resources\Clientes\RelationManagers\MovimientosRelationManager;
use App\Filament\Resources\Clientes\Schemas\ClienteInfolist;
use App\Filament\Resources\Clientes\Tables\ClientesTable;
use App\Models\Cliente;
use App\Models\User;
use App\Panel\AlcancePanel;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Clientes del programa (E10-06): lista y ficha de solo lectura, con el libro
 * de puntos y los cambios de nivel. El acceso lo decide `ClientePolicy` y el
 * alcance por rol `AlcancePanel`; el ajuste manual va por `ApplyAjusteManual`.
 */
class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clientes';

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'nombre_completo';

    protected static ?string $slug = 'clientes';

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return AlcancePanel::clientes(parent::getEloquentQuery(), $user);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClienteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientesTable::configure($table);
    }

    /**
     * @return array<int, class-string<RelationManager>>
     */
    public static function getRelations(): array
    {
        return [
            MovimientosRelationManager::class,
            CambiosNivelRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListClientes::route('/'),
            'view' => ViewCliente::route('/{record}'),
        ];
    }
}
