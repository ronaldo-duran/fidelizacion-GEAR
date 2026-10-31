<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transacciones;

use App\Filament\Resources\Transacciones\Pages\ListTransacciones;
use App\Filament\Resources\Transacciones\Tables\TransaccionesTable;
use App\Models\Transaccion;
use App\Models\User;
use App\Panel\AlcancePanel;
use App\Panel\AnulacionPanel;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Las compras de los comercios (E10-06): solo lista, con la anulación por
 * fila. El acceso lo decide `TransaccionPolicy`, el alcance `AlcancePanel` y
 * la anulación va por `AnnulCompra`.
 */
class TransaccionResource extends Resource
{
    protected static ?string $model = Transaccion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $modelLabel = 'transacción';

    protected static ?string $pluralModelLabel = 'transacciones';

    protected static ?string $navigationLabel = 'Transacciones';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'transacciones';

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $query = parent::getEloquentQuery()->with(['comercio', 'sede', 'cliente', 'registrador']);

        return AnulacionPanel::conAcreditacion(AlcancePanel::delComercio($query, $user));
    }

    public static function table(Table $table): Table
    {
        return TransaccionesTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListTransacciones::route('/'),
        ];
    }
}
