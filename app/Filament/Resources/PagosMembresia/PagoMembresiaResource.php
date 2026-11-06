<?php

declare(strict_types=1);

namespace App\Filament\Resources\PagosMembresia;

use App\Filament\Resources\Concerns\SpanishLabels;
use App\Filament\Resources\PagosMembresia\Pages\ListPagosMembresia;
use App\Filament\Resources\PagosMembresia\Tables\PagosMembresiaTable;
use App\Models\PagoMembresia;
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
 * Los pagos de membresía (E6-01), de solo lectura: quién pagó qué plan, por
 * cuánto y en qué estado quedó. Solo el webhook de la pasarela los cambia.
 */
class PagoMembresiaResource extends Resource
{
    use SpanishLabels;

    protected static ?string $model = PagoMembresia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'pago';

    protected static ?string $pluralModelLabel = 'pagos';

    protected static ?string $navigationLabel = 'Pagos';

    protected static string|null|UnitEnum $navigationGroup = 'Membresías';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'pagos-membresia';

    public static function canAccess(): bool
    {
        $usuario = Auth::user();

        return $usuario instanceof User && $usuario->activo && $usuario->esAdministradorDelGrupo();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['cliente', 'plan']);
    }

    public static function table(Table $table): Table
    {
        return PagosMembresiaTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPagosMembresia::route('/'),
        ];
    }
}
