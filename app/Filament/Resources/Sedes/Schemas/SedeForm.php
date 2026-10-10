<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sedes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SedeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('comercio_id')
                    ->label('Comercio')
                    ->relationship('comercio', 'razon_social')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('direccion')
                    ->label('Dirección')
                    ->required()
                    ->maxLength(255),
                Toggle::make('activa')
                    ->label('Activa')
                    ->default(true),
            ]);
    }
}
