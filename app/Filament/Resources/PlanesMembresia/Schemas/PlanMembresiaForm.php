<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanesMembresia\Schemas;

use App\Enums\Nivel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlanMembresiaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(80),
                Select::make('nivel')
                    ->label('Nivel que otorga')
                    ->options(Nivel::class)
                    ->required(),
                TextInput::make('duracion_meses')
                    ->label('Duración en meses')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(36)
                    ->required(),
                TextInput::make('precio_cop')
                    ->label('Precio')
                    ->prefix('$')
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->helperText('En pesos enteros. Un cambio de precio solo afecta los pagos que se inicien después.'),
                Toggle::make('activo')
                    ->label('Activo')
                    ->default(true)
                    ->helperText('Un plan inactivo no aparece en la app. Los planes no se borran.'),
            ]);
    }
}
