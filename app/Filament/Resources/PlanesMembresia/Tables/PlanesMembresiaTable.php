<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanesMembresia\Tables;

use App\Support\FormatoColombiano;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanesMembresiaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('duracion_meses')
            ->columns([
                TextColumn::make('nombre')->label('Plan'),
                TextColumn::make('nivel')->label('Nivel')->badge(),
                TextColumn::make('duracion_meses')
                    ->label('Duración')
                    ->formatStateUsing(static fn (int $state): string => $state === 1 ? '1 mes' : "{$state} meses"),
                TextColumn::make('precio_cop')
                    ->label('Precio')
                    ->formatStateUsing(static fn (int $state): string => FormatoColombiano::pesos($state)),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
