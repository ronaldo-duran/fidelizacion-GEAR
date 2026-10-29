<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficios;

use App\Filament\Resources\Beneficios\Pages\CreateBeneficio;
use App\Filament\Resources\Beneficios\Pages\EditBeneficio;
use App\Filament\Resources\Beneficios\Pages\ListBeneficios;
use App\Filament\Resources\Beneficios\Schemas\BeneficioForm;
use App\Filament\Resources\Beneficios\Tables\BeneficiosTable;
use App\Models\Beneficio;
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

/**
 * Catálogo de beneficios (E5-01). El acceso lo decide `BeneficioPolicy` y
 * guardar pasa por `SaveBeneficio`, que valida todo; el recurso no tiene
 * lógica de negocio.
 */
class BeneficioResource extends Resource
{
    protected static ?string $model = Beneficio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $modelLabel = 'beneficio';

    protected static ?string $pluralModelLabel = 'beneficios';

    protected static ?string $navigationLabel = 'Beneficios';

    protected static string|null|UnitEnum $navigationGroup = 'Beneficios';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'beneficios';

    protected static ?string $recordTitleAttribute = 'nombre';

    /**
     * Un administrador de comercio solo ve los beneficios de su comercio.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('comercio');
        $user = Auth::user();

        if ($user instanceof User && ! $user->esAdministradorDelGrupo()) {
            $query->getQuery()->where('comercio_id', $user->comercio_id ?? 0);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return BeneficioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BeneficiosTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListBeneficios::route('/'),
            'create' => CreateBeneficio::route('/create'),
            'edit' => EditBeneficio::route('/{record}/edit'),
        ];
    }
}
