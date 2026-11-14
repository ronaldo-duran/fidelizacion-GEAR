<?php

declare(strict_types=1);

namespace App\Filament\Resources\CredencialesApi;

use App\Enums\RolUsuario;
use App\Filament\Resources\Concerns\SpanishLabels;
use App\Filament\Resources\CredencialesApi\Pages\ListCredencialesApi;
use App\Filament\Resources\CredencialesApi\Tables\CredencialesApiTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Credenciales de la API pública (E9-01, regla 4): cada una es un usuario de
 * integración de una sede. Se crean, rotan y revocan con sus Actions; no se
 * editan. El administrador del grupo ve todas y el de comercio, las suyas.
 */
class CredencialApiResource extends Resource
{
    use SpanishLabels;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'credenciales-api';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $modelLabel = 'credencial API';

    protected static ?string $pluralModelLabel = 'credenciales API';

    protected static ?string $navigationLabel = 'Credenciales API';

    protected static string|null|UnitEnum $navigationGroup = 'Comercios';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $usuario = Auth::user();

        return $usuario instanceof User
            && ($usuario->esAdministradorDelGrupo() || $usuario->esAdministradorDeComercio());
    }

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('rol', RolUsuario::Integracion->value)
            ->with(['comercio', 'sede', 'tokens']);
        $usuario = Auth::user();

        if ($usuario instanceof User && ! $usuario->esAdministradorDelGrupo()) {
            $query->where('comercio_id', $usuario->comercio_id ?? 0);
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return CredencialesApiTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListCredencialesApi::route('/'),
        ];
    }
}
