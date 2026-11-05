<?php

declare(strict_types=1);

namespace App\Filament\Resources\EnviosNotificacion;

use App\Filament\Resources\EnviosNotificacion\Pages\ListEnviosNotificacion;
use App\Filament\Resources\EnviosNotificacion\Tables\EnviosNotificacionTable;
use App\Models\EnvioNotificacion;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * El registro de avisos enviados (E7-01), para soporte: qué se le envió a
 * cada cliente, por qué canal, y si salió, se omitió o falló. Solo lectura.
 */
class EnvioNotificacionResource extends Resource
{
    protected static ?string $model = EnvioNotificacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?string $modelLabel = 'aviso enviado';

    protected static ?string $pluralModelLabel = 'avisos enviados';

    protected static ?string $navigationLabel = 'Avisos enviados';

    protected static string|null|UnitEnum $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'avisos-enviados';

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('cliente');
    }

    public static function table(Table $table): Table
    {
        return EnviosNotificacionTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListEnviosNotificacion::route('/'),
        ];
    }
}
