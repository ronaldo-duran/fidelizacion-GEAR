<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanesMembresia;

use App\Filament\Resources\Concerns\SpanishLabels;
use App\Filament\Resources\PlanesMembresia\Pages\CreatePlanMembresia;
use App\Filament\Resources\PlanesMembresia\Pages\EditPlanMembresia;
use App\Filament\Resources\PlanesMembresia\Pages\ListPlanesMembresia;
use App\Filament\Resources\PlanesMembresia\Schemas\PlanMembresiaForm;
use App\Filament\Resources\PlanesMembresia\Tables\PlanesMembresiaTable;
use App\Models\PlanMembresia;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Los planes de membresía (E6-01): nombre, nivel, meses y precio en pesos
 * enteros. Solo el administrador del grupo. Un plan no se borra (no hay
 * acción de borrar): se desactiva. Cambiar el precio solo afecta a los pagos
 * que se inicien después, porque cada pago guarda su copia del plan.
 */
class PlanMembresiaResource extends Resource
{
    use SpanishLabels;

    protected static ?string $model = PlanMembresia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $modelLabel = 'plan';

    protected static ?string $pluralModelLabel = 'planes';

    protected static ?string $navigationLabel = 'Planes';

    protected static string|null|UnitEnum $navigationGroup = 'Membresías';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'planes-membresia';

    public static function canAccess(): bool
    {
        $usuario = Auth::user();

        return $usuario instanceof User && $usuario->activo && $usuario->esAdministradorDelGrupo();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PlanMembresiaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanesMembresiaTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPlanesMembresia::route('/'),
            'create' => CreatePlanMembresia::route('/create'),
            'edit' => EditPlanMembresia::route('/{record}/edit'),
        ];
    }
}
