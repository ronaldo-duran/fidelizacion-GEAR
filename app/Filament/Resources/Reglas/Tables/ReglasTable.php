<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reglas\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReglasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('version')
                    ->label('Versión')
                    ->numeric(),
                TextColumn::make('vigente_desde')
                    ->label('Vigente desde')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->label('Nueva versión'),
            ]);
    }
}
