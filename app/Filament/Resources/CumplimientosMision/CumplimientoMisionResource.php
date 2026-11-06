<?php

declare(strict_types=1);

namespace App\Filament\Resources\CumplimientosMision;

use App\Enums\EstadoCumplimiento;
use App\Filament\Resources\BaseResource;
use App\Filament\Resources\CumplimientosMision\Pages\ListCumplimientosMision;
use App\Filament\Resources\CumplimientosMision\Tables\CumplimientosMisionTable;
use App\Models\CumplimientoMision;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Cola de cumplimientos de misiones del comercio (E3-06). La ve quien tiene
 * `aprueba_misiones` (lo decide `CumplimientoMisionPolicy`); aprobar y
 * rechazar pasan por las Actions de promociones. No se crean ni se editan
 * aquí: los envía el cliente desde la PWA.
 */
class CumplimientoMisionResource extends BaseResource
{
    protected static ?string $model = CumplimientoMision::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'cumplimiento';

    protected static ?string $pluralModelLabel = 'cumplimientos';

    protected static ?string $navigationLabel = 'Cumplimientos';

    protected static string|null|UnitEnum $navigationGroup = 'Niveles y reglas';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'cumplimientos';

    /**
     * Solo los del comercio del usuario.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['cliente', 'regla', 'revisor']);
        $user = Auth::user();
        $query->getQuery()->where('comercio_id', $user instanceof User ? ($user->comercio_id ?? 0) : 0);

        return $query;
    }

    /**
     * Los pendientes de revisar, para que no se queden en la cola.
     */
    public static function getNavigationBadge(): ?string
    {
        $pendientes = static::getEloquentQuery()
            ->toBase()
            ->where('estado', EstadoCumplimiento::Pendiente->value)
            ->count();

        return $pendientes > 0 ? (string) $pendientes : null;
    }

    public static function table(Table $table): Table
    {
        return CumplimientosMisionTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListCumplimientosMision::route('/'),
        ];
    }
}
