<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlantillasNotificacion;

use App\Filament\Resources\PlantillasNotificacion\Pages\EditPlantillaNotificacion;
use App\Filament\Resources\PlantillasNotificacion\Pages\ListPlantillasNotificacion;
use App\Filament\Resources\PlantillasNotificacion\Schemas\PlantillaNotificacionForm;
use App\Filament\Resources\PlantillasNotificacion\Tables\PlantillasNotificacionTable;
use App\Models\PlantillaNotificacion;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Las plantillas de los avisos al cliente (E7-01): asunto, cuerpo y si
 * están activas. Una por evento; no se crean ni se borran. Guardar pasa por
 * `UpdatePlantillaNotificacion`, que valida la lista blanca de variables.
 */
class PlantillaNotificacionResource extends Resource
{
    protected static ?string $model = PlantillaNotificacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $modelLabel = 'plantilla de aviso';

    protected static ?string $pluralModelLabel = 'plantillas de avisos';

    protected static ?string $navigationLabel = 'Plantillas de avisos';

    protected static string|null|UnitEnum $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'plantillas-avisos';

    public static function form(Schema $schema): Schema
    {
        return PlantillaNotificacionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlantillasNotificacionTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPlantillasNotificacion::route('/'),
            'edit' => EditPlantillaNotificacion::route('/{record}/edit'),
        ];
    }
}
