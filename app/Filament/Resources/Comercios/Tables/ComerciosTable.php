<?php

declare(strict_types=1);

namespace App\Filament\Resources\Comercios\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ComerciosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('razon_social')
                    ->label('Razón social')
                    ->searchable(),
                TextColumn::make('nit')
                    ->label('NIT')
                    ->searchable(),
                TextColumn::make('categoria.nombre')
                    ->label('Categoría')
                    ->sortable(),
                TextColumn::make('sedes_count')
                    ->label('Sedes')
                    ->counts('sedes'),
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
