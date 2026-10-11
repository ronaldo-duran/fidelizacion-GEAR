<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reglas;

use App\Filament\Resources\Reglas\Pages\CreateRegla;
use App\Filament\Resources\Reglas\Pages\ListReglas;
use App\Filament\Resources\Reglas\Pages\NewReglaVersion;
use App\Filament\Resources\Reglas\Pages\ViewRegla;
use App\Filament\Resources\Reglas\RelationManagers\VersionsRelationManager;
use App\Filament\Resources\Reglas\Schemas\ReglaForm;
use App\Filament\Resources\Reglas\Schemas\ReglaInfolist;
use App\Filament\Resources\Reglas\Tables\ReglasTable;
use App\Models\Regla;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Rule engine (E3-01). Access is decided by `ReglaPolicy`: only the group
 * administrator. There is no in-place editing or deleting: "editing" means
 * saving a new version through the actions.
 */
class ReglaResource extends Resource
{
    protected static ?string $model = Regla::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $modelLabel = 'regla';

    protected static ?string $pluralModelLabel = 'reglas';

    protected static ?string $navigationLabel = 'Reglas';

    protected static string|null|UnitEnum $navigationGroup = 'Niveles y reglas';

    protected static ?string $recordTitleAttribute = 'codigo';

    /**
     * Each rule appears once, by its latest version. Older versions live in
     * the history.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $query->getQuery()->whereNull('vigente_hasta');

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return ReglaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReglaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReglasTable::configure($table);
    }

    /**
     * @return array<int, class-string<RelationManager>>
     */
    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListReglas::route('/'),
            'create' => CreateRegla::route('/create'),
            'view' => ViewRegla::route('/{record}'),
            'edit' => NewReglaVersion::route('/{record}/nueva-version'),
        ];
    }
}
