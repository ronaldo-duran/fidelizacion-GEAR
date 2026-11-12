<?php

declare(strict_types=1);

namespace App\Filament\Resources\Comercios\Schemas;

use App\Models\Comercio;
use App\Models\ComercioPromocionLimite;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

class ComercioForm
{
    /**
     * Los campos que solo cambia el grupo: de la categoría sale la tasa de
     * puntos, y el NIT y el estado activo son del catálogo del grupo.
     */
    public const DATOS_DEL_GRUPO = ['nit', 'categoria_id', 'activo'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('razon_social')
                    ->label('Razón social')
                    ->required()
                    ->maxLength(255),
                TextInput::make('nit')
                    ->label('NIT')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->disabled(self::noEsDelGrupo(...)),
                Select::make('categoria_id')
                    ->label('Categoría')
                    ->relationship('categoria', 'nombre')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(self::noEsDelGrupo(...)),
                Toggle::make('activo')
                    ->label('Activo')
                    ->default(true)
                    ->helperText('Un comercio inactivo no puede registrar compras.')
                    ->disabled(self::noEsDelGrupo(...)),
                self::promociones(),
            ]);
    }

    /**
     * Un campo bloqueado no llega en los datos del formulario; `EditComercio`
     * además los descarta en el servidor.
     */
    private static function noEsDelGrupo(): bool
    {
        return ! Gate::allows('updateDatosDelGrupo', Comercio::class);
    }

    /**
     * Lo que el comercio puede dar en misiones (E3-06). Solo lo ve y lo
     * cambia el grupo; el comercio no edita sus propios límites.
     */
    private static function promociones(): Section
    {
        return Section::make('Promociones')
            ->key('promocion')
            ->description('Si el comercio puede crear misiones y cuántos puntos puede dar cada una.')
            ->statePath('promocion')
            ->columns(2)
            ->columnSpanFull()
            ->visible(static fn (string $operation): bool => $operation === 'edit'
                && Gate::allows('update', ComercioPromocionLimite::class))
            ->schema([
                Toggle::make('permite_misiones')
                    ->label('Puede crear misiones')
                    ->live(),
                TextInput::make('puntos_max_por_mision')
                    ->label('Máximo de puntos por misión')
                    ->helperText('Bajarlo no cambia las misiones guardadas: aplica a la próxima versión activa.')
                    ->integer()
                    ->minValue(static fn (Get $get): int => $get('permite_misiones') === true ? 1 : 0)
                    ->required(),
            ]);
    }
}
