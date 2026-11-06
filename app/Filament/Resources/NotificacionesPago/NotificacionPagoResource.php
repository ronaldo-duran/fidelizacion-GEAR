<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificacionesPago;

use App\Filament\Resources\Concerns\SpanishLabels;
use App\Filament\Resources\NotificacionesPago\Pages\ListNotificacionesPago;
use App\Filament\Resources\NotificacionesPago\Tables\NotificacionesPagoTable;
use App\Models\NotificacionPago;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * El registro de toda notificación de la pasarela (E6-01), válida o no, y
 * lo que se hizo con ella. Solo lectura; las «Para revisión» piden mirar el
 * caso con el cliente (un rechazo tardío, un segundo cobro, un id ajeno).
 */
class NotificacionPagoResource extends Resource
{
    use SpanishLabels;

    protected static ?string $model = NotificacionPago::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $modelLabel = 'notificación de pago';

    protected static ?string $pluralModelLabel = 'notificaciones de pago';

    protected static ?string $navigationLabel = 'Notificaciones de pago';

    protected static string|null|UnitEnum $navigationGroup = 'Membresías';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'notificaciones-pago';

    public static function canAccess(): bool
    {
        $usuario = Auth::user();

        return $usuario instanceof User && $usuario->activo && $usuario->esAdministradorDelGrupo();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return NotificacionesPagoTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListNotificacionesPago::route('/'),
        ];
    }
}
