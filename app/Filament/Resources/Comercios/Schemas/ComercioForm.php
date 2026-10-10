<?php

declare(strict_types=1);

namespace App\Filament\Resources\Comercios\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ComercioForm
{
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
                    ->unique(ignoreRecord: true),
                Select::make('categoria_id')
                    ->label('Categoría')
                    ->relationship('categoria', 'nombre')
                    ->searchable()
                    ->preload()
                    ->required(),
                Toggle::make('activo')
                    ->label('Activo')
                    ->default(true)
                    ->helperText('Un comercio inactivo no puede registrar compras.'),
            ]);
    }
}
